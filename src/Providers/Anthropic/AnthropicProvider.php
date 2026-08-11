<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\Anthropic;

use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\DTO\ImagePart;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\DTO\StreamChunk;
use EorBah545\AI\DTO\TextPart;
use EorBah545\AI\DTO\ToolCall;
use EorBah545\AI\DTO\ToolResultPart;
use EorBah545\AI\DTO\ToolUsePart;
use EorBah545\AI\DTO\Usage;
use EorBah545\AI\Enum\FinishReason;
use EorBah545\AI\Enum\Role;
use EorBah545\AI\Exceptions\AiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Provider Anthropic (Messages API).
 *
 * Docs officielles :
 * - Messages : https://docs.claude.com/en/api/messages
 * - Tool use : https://docs.claude.com/en/docs/agents-and-tools/tool-use/overview
 * - Streaming : https://docs.claude.com/en/api/messages-streaming
 * - Prompt caching : https://docs.claude.com/en/docs/build-with-claude/prompt-caching
 *
 * Différences clés avec l'API OpenAI, gérées en interne par cette classe :
 * - le "system prompt" est un paramètre séparé, pas un message ;
 * - il n'y a pas de rôle "tool" : un résultat d'outil est un content block
 *   de type "tool_result" à l'intérieur d'un message "user" ;
 * - max_tokens est obligatoire ;
 * - pas de "response_format" natif : le structured output est simulé en
 *   forçant l'appel d'un outil synthétique dont le schéma est $responseSchema
 *   (tool_choice forcé), puis en réinjectant son "input" comme texte JSON.
 * - le cache de prompt se marque avec TextPart::$cache=true (voir Message::system()).
 */
final class AnthropicProvider implements ProviderInterface
{
    private const STRUCTURED_OUTPUT_TOOL = '__structured_response__';

    private Client $http;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiVersion = '2023-06-01',
        ?Client $httpClient = null,
    ) {
        $this->http = $httpClient ?? new Client(['base_uri' => 'https://api.anthropic.com/v1/', 'timeout' => 120]);
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $payload = $this->buildPayload($request, stream: false);
        $data = $this->request('messages', $payload);

        return $this->parseResponse($data);
    }

    public function stream(ChatRequest $request): \Generator
    {
        $payload = $this->buildPayload($request, stream: true);

        try {
            $response = $this->http->post('messages', [
                'headers' => $this->headers(),
                'json' => $payload,
                'stream' => true,
            ]);
        } catch (GuzzleException $e) {
            throw new AiException('Échec de la requête de streaming Anthropic : ' . $e->getMessage(), [], $e);
        }

        $body = $response->getBody();
        $buffer = '';
        $blocks = []; // index => ['type' => 'text'|'tool_use', 'text' => '', 'id' => '', 'name' => '', 'json' => '']
        $inputTokens = 0;

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $eventType = null;
                $data = null;
                foreach (explode("\n", $event) as $line) {
                    if (str_starts_with($line, 'event:')) {
                        $eventType = trim(substr($line, 6));
                    } elseif (str_starts_with($line, 'data:')) {
                        $data = json_decode(trim(substr($line, 5)), true);
                    }
                }
                if ($eventType === null || $data === null) {
                    continue;
                }

                switch ($eventType) {
                    case 'message_start':
                        $inputTokens = $data['message']['usage']['input_tokens'] ?? 0;
                        break;

                    case 'content_block_start':
                        $index = $data['index'];
                        $block = $data['content_block'];
                        $blocks[$index] = $block['type'] === 'tool_use'
                            ? ['type' => 'tool_use', 'id' => $block['id'], 'name' => $block['name'], 'json' => '']
                            : ['type' => 'text', 'text' => ''];
                        break;

                    case 'content_block_delta':
                        $index = $data['index'];
                        $delta = $data['delta'];
                        if ($delta['type'] === 'text_delta') {
                            $blocks[$index]['text'] .= $delta['text'];
                            yield new StreamChunk(deltaText: $delta['text']);
                        } elseif ($delta['type'] === 'input_json_delta') {
                            $blocks[$index]['json'] .= $delta['partial_json'];
                        }
                        break;

                    case 'content_block_stop':
                        $index = $data['index'];
                        $block = $blocks[$index] ?? null;
                        if ($block !== null && $block['type'] === 'tool_use') {
                            yield new StreamChunk(toolCall: new ToolCall(
                                $block['id'],
                                $block['name'],
                                json_decode($block['json'] ?: '{}', true) ?? []
                            ));
                        }
                        break;

                    case 'message_delta':
                        $stopReason = $data['delta']['stop_reason'] ?? 'end_turn';
                        $usage = new Usage(
                            $inputTokens,
                            $data['usage']['output_tokens'] ?? 0,
                            $data['usage']['cache_read_input_tokens'] ?? 0,
                        );
                        yield new StreamChunk(done: true, finishReason: $this->mapStopReason($stopReason), usage: $usage);
                        break;
                }
            }
        }
    }

    private function buildPayload(ChatRequest $request, bool $stream): array
    {
        [$system, $messages] = $this->mapMessages($request->messages);

        $payload = [
            'model' => $request->model,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens ?? 4096,
            'temperature' => $request->temperature,
            'stream' => $stream,
        ];
        if ($system !== null) {
            $payload['system'] = $system;
        }

        $tools = [];
        if ($request->tools !== null) {
            foreach ($request->tools->all() as $tool) {
                $tools[] = ['name' => $tool->name, 'description' => $tool->description, 'input_schema' => $tool->parameters];
            }
        }

        if ($request->responseSchema !== null) {
            // Pas de "response_format" natif chez Anthropic : on force l'appel
            // d'un outil synthétique dont le schéma EST le schéma de sortie voulu.
            $tools[] = [
                'name' => self::STRUCTURED_OUTPUT_TOOL,
                'description' => 'Renvoie la réponse structurée finale selon le schéma fourni.',
                'input_schema' => $request->responseSchema,
            ];
            $payload['tool_choice'] = ['type' => 'tool', 'name' => self::STRUCTURED_OUTPUT_TOOL];
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        return $payload;
    }

    /**
     * @param Message[] $messages
     * @return array{0: array<int,array<string,mixed>>|string|null, 1: array<int,array<string,mixed>>}
     */
    private function mapMessages(array $messages): array
    {
        $system = null;
        $mapped = [];

        foreach ($messages as $message) {
            if ($message->role === Role::System) {
                $system = is_string($message->content) ? $message->content : $this->blocksFromParts($message->content);

                continue;
            }

            if (is_string($message->content)) {
                $mapped[] = ['role' => $message->role->value, 'content' => $message->content];

                continue;
            }

            $blocks = [];
            $isToolResult = false;

            foreach ($message->content as $part) {
                if ($part instanceof TextPart) {
                    $block = ['type' => 'text', 'text' => $part->text];
                    if ($part->cache) {
                        $block['cache_control'] = ['type' => 'ephemeral'];
                    }
                    $blocks[] = $block;
                } elseif ($part instanceof ImagePart) {
                    $blocks[] = ['type' => 'image', 'source' => $part->url !== null
                        ? ['type' => 'url', 'url' => $part->url]
                        : ['type' => 'base64', 'media_type' => $part->mimeType, 'data' => $part->base64]];
                } elseif ($part instanceof ToolUsePart) {
                    $blocks[] = ['type' => 'tool_use', 'id' => $part->id, 'name' => $part->name, 'input' => $part->arguments];
                } elseif ($part instanceof ToolResultPart) {
                    $isToolResult = true;
                    $blocks[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $part->toolUseId,
                        'content' => $part->content,
                        'is_error' => $part->isError,
                    ];
                }
            }

            // Un résultat d'outil est envoyé côté "user" chez Anthropic (pas de rôle "tool").
            $role = $isToolResult ? 'user' : $message->role->value;
            $mapped[] = ['role' => $role, 'content' => $blocks];
        }

        return [$system, $mapped];
    }

    /**
     * @param array<int,\AI\DTO\ContentPartInterface> $parts
     * @return array<int,array<string,mixed>>
     */
    private function blocksFromParts(array $parts): array
    {
        $blocks = [];
        foreach ($parts as $part) {
            if ($part instanceof TextPart) {
                $block = ['type' => 'text', 'text' => $part->text];
                if ($part->cache) {
                    $block['cache_control'] = ['type' => 'ephemeral'];
                }
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    private function parseResponse(array $data): ChatResponse
    {
        $text = null;
        $toolCalls = [];
        $structuredJson = null;

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $text = ($text ?? '') . $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                if ($block['name'] === self::STRUCTURED_OUTPUT_TOOL) {
                    $structuredJson = json_encode($block['input']);
                } else {
                    $toolCalls[] = new ToolCall($block['id'], $block['name'], $block['input']);
                }
            }
        }

        return new ChatResponse(
            text: $structuredJson ?? $text,
            toolCalls: $toolCalls,
            finishReason: $structuredJson !== null ? FinishReason::Stop : $this->mapStopReason($data['stop_reason'] ?? 'end_turn'),
            usage: new Usage(
                $data['usage']['input_tokens'] ?? 0,
                $data['usage']['output_tokens'] ?? 0,
                $data['usage']['cache_read_input_tokens'] ?? 0,
            ),
            raw: $data,
        );
    }

    private function mapStopReason(string $reason): FinishReason
    {
        return match ($reason) {
            'end_turn', 'stop_sequence' => FinishReason::Stop,
            'tool_use' => FinishReason::ToolCalls,
            'max_tokens' => FinishReason::Length,
            default => FinishReason::Error,
        };
    }

    private function headers(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'content-type' => 'application/json',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function request(string $uri, array $payload): array
    {
        try {
            $response = $this->http->post($uri, ['headers' => $this->headers(), 'json' => $payload]);
        } catch (GuzzleException $e) {
            throw new AiException("Échec de l'appel Anthropic {$uri} : " . $e->getMessage(), [], $e);
        }

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
