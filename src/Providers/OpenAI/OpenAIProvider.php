<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\OpenAI;

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
 * Provider OpenAI (Chat Completions API) — et base réutilisable pour tout
 * fournisseur exposant une API "compatible OpenAI" (xAI, DeepSeek, Mistral...),
 * qui se contentent de changer $baseUri et le préfixe des modèles.
 *
 * Docs officielles :
 * - Chat Completions : https://platform.openai.com/docs/api-reference/chat
 * - Function calling : https://platform.openai.com/docs/guides/function-calling
 * - Structured Outputs : https://platform.openai.com/docs/guides/structured-outputs
 * - Streaming : https://platform.openai.com/docs/api-reference/streaming
 * - Prompt caching : automatique côté OpenAI dès que le préfixe de prompt
 *   dépasse 1024 tokens — rien à faire côté appelant, visible dans
 *   usage.prompt_tokens_details.cached_tokens.
 */
class OpenAIProvider implements ProviderInterface
{
    protected Client $http;

    public function __construct(
        protected readonly string $apiKey,
        protected readonly string $baseUri = 'https://api.openai.com/v1',
        ?Client $httpClient = null,
    ) {
        $this->http = $httpClient ?? new Client(['base_uri' => rtrim($baseUri, '/') . '/', 'timeout' => 120]);
    }

    public function getName(): string
    {
        return 'openai';
    }

    /**
     * Expose la construction du payload d'une requête (utilisé par
     * OpenAIBatchClient pour construire les lignes JSONL d'un batch).
     */
    public function toRequestPayload(ChatRequest $request): array
    {
        return $this->buildPayload($request, stream: false);
    }

    /**
     * Expose le parsing d'une réponse brute (utilisé par OpenAIBatchClient).
     */
    public function parseChatResponse(array $data): ChatResponse
    {
        return $this->parseResponse($data);
    }


    public function chat(ChatRequest $request): ChatResponse
    {
        $payload = $this->buildPayload($request, stream: false);
        $data = $this->request('chat/completions', $payload);

        return $this->parseResponse($data);
    }

    public function stream(ChatRequest $request): \Generator
    {
        $payload = $this->buildPayload($request, stream: true);
        $payload['stream_options'] = ['include_usage' => true];

        try {
            $response = $this->http->post('chat/completions', [
                'headers' => $this->headers(),
                'json' => $payload,
                'stream' => true,
            ]);
        } catch (GuzzleException $e) {
            throw new AiException('Échec de la requête de streaming OpenAI : ' . $e->getMessage(), [], $e);
        }

        $body = $response->getBody();
        $buffer = '';

        // Accumulateurs pour les tool calls, dont les arguments arrivent
        // fragmentés sur plusieurs chunks (index => ['id' =>, 'name' =>, 'arguments' => '...'])
        $toolCallsAcc = [];

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $event) as $line) {
                    if (!str_starts_with($line, 'data:')) {
                        continue;
                    }
                    $json = trim(substr($line, 5));
                    if ($json === '[DONE]') {
                        continue;
                    }

                    $chunk = json_decode($json, true);
                    if ($chunk === null) {
                        continue;
                    }

                    $delta = $chunk['choices'][0]['delta'] ?? [];
                    $finishReasonRaw = $chunk['choices'][0]['finish_reason'] ?? null;

                    if (isset($delta['content']) && $delta['content'] !== '') {
                        yield new StreamChunk(deltaText: $delta['content']);
                    }

                    foreach ($delta['tool_calls'] ?? [] as $tc) {
                        $index = $tc['index'];
                        $toolCallsAcc[$index]['id'] ??= $tc['id'] ?? '';
                        $toolCallsAcc[$index]['name'] ??= $tc['function']['name'] ?? '';
                        $toolCallsAcc[$index]['arguments'] = ($toolCallsAcc[$index]['arguments'] ?? '') . ($tc['function']['arguments'] ?? '');
                    }

                    if ($finishReasonRaw !== null) {
                        foreach ($toolCallsAcc as $tc) {
                            yield new StreamChunk(toolCall: new ToolCall(
                                $tc['id'],
                                $tc['name'],
                                json_decode($tc['arguments'] ?: '{}', true) ?? []
                            ));
                        }

                        $usage = isset($chunk['usage']) ? new Usage(
                            $chunk['usage']['prompt_tokens'] ?? 0,
                            $chunk['usage']['completion_tokens'] ?? 0,
                            $chunk['usage']['prompt_tokens_details']['cached_tokens'] ?? 0,
                        ) : null;

                        yield new StreamChunk(done: true, finishReason: $this->mapFinishReason($finishReasonRaw), usage: $usage);
                    }
                }
            }
        }
    }

    protected function buildPayload(ChatRequest $request, bool $stream): array
    {
        $payload = [
            'model' => $request->model,
            'messages' => $this->mapMessages($request->messages),
            'temperature' => $request->temperature,
            'stream' => $stream,
        ];

        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->tools !== null && !$request->tools->isEmpty()) {
            $payload['tools'] = array_map(static fn ($tool) => [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters,
                ],
            ], $request->tools->all());
        }

        if ($request->responseSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'response',
                    'schema' => $request->responseSchema,
                    'strict' => true,
                ],
            ];
        }

        if ($request->reasoningEffort !== null) {
            $payload['reasoning_effort'] = $request->reasoningEffort;
        }

        return $payload;
    }

    /**
     * @param Message[] $messages
     */
    protected function mapMessages(array $messages): array
    {
        $mapped = [];

        foreach ($messages as $message) {
            if (is_string($message->content)) {
                $mapped[] = ['role' => $message->role->value, 'content' => $message->content];

                continue;
            }

            // Contenu structuré : soit des appels d'outils (assistant), soit
            // un résultat d'outil (tool), soit du texte + images (user).
            $toolCalls = [];
            $toolResults = [];
            $parts = [];

            foreach ($message->content as $part) {
                if ($part instanceof ToolUsePart) {
                    $toolCalls[] = [
                        'id' => $part->id,
                        'type' => 'function',
                        'function' => ['name' => $part->name, 'arguments' => json_encode($part->arguments)],
                    ];
                } elseif ($part instanceof ToolResultPart) {
                    $toolResults[] = $part;
                } elseif ($part instanceof TextPart) {
                    $parts[] = ['type' => 'text', 'text' => $part->text];
                } elseif ($part instanceof ImagePart) {
                    $parts[] = ['type' => 'image_url', 'image_url' => [
                        'url' => $part->url ?? "data:{$part->mimeType};base64,{$part->base64}",
                    ]];
                }
            }

            if ($toolCalls !== []) {
                $mapped[] = ['role' => 'assistant', 'content' => null, 'tool_calls' => $toolCalls];

                continue;
            }

            foreach ($toolResults as $result) {
                $mapped[] = ['role' => 'tool', 'tool_call_id' => $result->toolUseId, 'content' => $result->content];
            }
            if ($toolResults !== []) {
                continue;
            }

            $mapped[] = ['role' => $message->role->value, 'content' => $parts];
        }

        return $mapped;
    }

    protected function parseResponse(array $data): ChatResponse
    {
        $choice = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $tc) {
            $toolCalls[] = new ToolCall(
                $tc['id'],
                $tc['function']['name'],
                json_decode($tc['function']['arguments'] ?: '{}', true) ?? []
            );
        }

        return new ChatResponse(
            text: $message['content'] ?? null,
            toolCalls: $toolCalls,
            finishReason: $this->mapFinishReason($choice['finish_reason'] ?? 'stop'),
            usage: new Usage(
                $data['usage']['prompt_tokens'] ?? 0,
                $data['usage']['completion_tokens'] ?? 0,
                $data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0,
            ),
            raw: $data,
        );
    }

    protected function mapFinishReason(string $reason): FinishReason
    {
        return match ($reason) {
            'stop' => FinishReason::Stop,
            'tool_calls', 'function_call' => FinishReason::ToolCalls,
            'length' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            default => FinishReason::Error,
        };
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected function request(string $uri, array $payload): array
    {
        try {
            $response = $this->http->post($uri, ['headers' => $this->headers(), 'json' => $payload]);
        } catch (GuzzleException $e) {
            throw new AiException("Échec de l'appel {$this->getName()} {$uri} : " . $e->getMessage(), [], $e);
        }

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
