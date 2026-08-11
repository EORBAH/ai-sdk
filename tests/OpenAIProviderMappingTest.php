<?php

declare(strict_types=1);

namespace EorBah545\AI\Tests;

use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\DTO\ToolCall;
use EorBah545\AI\Enum\FinishReason;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use EorBah545\AI\ToolRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Teste la construction du payload et le parsing des réponses sans passer
 * par le réseau : on instancie le provider sans exécuter son constructeur
 * (donc sans avoir besoin d'un vrai client Guzzle) et on invoque directement
 * ses méthodes de mapping, qui sont pures (aucun appel HTTP).
 */
final class OpenAIProviderMappingTest extends TestCase
{
    private OpenAIProvider $provider;

    protected function setUp(): void
    {
        $this->provider = (new ReflectionClass(OpenAIProvider::class))->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, array $args): mixed
    {
        $m = new ReflectionMethod($this->provider, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->provider, $args);
    }

    public function test_build_payload_includes_tools_and_response_schema(): void
    {
        $tools = new ToolRegistry();
        $tools->register(new Tool('get_weather', 'Météo', Schema::object(['city' => Schema::string()], ['city']), fn () => 'ok'));

        $request = new ChatRequest(
            model: 'gpt-4.1',
            messages: [Message::system('Tu es utile.'), Message::user('Météo ?')],
            tools: $tools,
            responseSchema: Schema::object(['reponse' => Schema::string()], ['reponse']),
            temperature: 0.5,
        );

        $payload = $this->invoke('buildPayload', [$request, false]);

        self::assertSame('gpt-4.1', $payload['model']);
        self::assertSame(['role' => 'system', 'content' => 'Tu es utile.'], $payload['messages'][0]);
        self::assertSame('get_weather', $payload['tools'][0]['function']['name']);
        self::assertSame('json_schema', $payload['response_format']['type']);
        self::assertSame(0.5, $payload['temperature']);
    }

    public function test_build_payload_maps_tool_calls_and_tool_results(): void
    {
        $request = new ChatRequest('gpt-4.1', [
            Message::user('Météo ?'),
            Message::assistantToolCalls([new ToolCall('call_1', 'get_weather', ['city' => 'Conakry'])]),
            Message::toolResult('call_1', 'Il fait beau'),
        ]);

        $payload = $this->invoke('buildPayload', [$request, false]);
        $messages = $payload['messages'];

        self::assertSame('assistant', $messages[1]['role']);
        self::assertNull($messages[1]['content']);
        self::assertSame('get_weather', $messages[1]['tool_calls'][0]['function']['name']);
        self::assertSame(['city' => 'Conakry'], json_decode($messages[1]['tool_calls'][0]['function']['arguments'], true));

        self::assertSame('tool', $messages[2]['role']);
        self::assertSame('call_1', $messages[2]['tool_call_id']);
        self::assertSame('Il fait beau', $messages[2]['content']);
    }

    public function test_parse_response_extracts_text_and_usage(): void
    {
        $data = [
            'choices' => [['message' => ['content' => 'Il fait beau.', 'tool_calls' => null], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 8, 'prompt_tokens_details' => ['cached_tokens' => 5]],
        ];

        $response = $this->invoke('parseResponse', [$data]);

        self::assertSame('Il fait beau.', $response->text);
        self::assertSame(20, $response->usage->inputTokens);
        self::assertSame(5, $response->usage->cachedInputTokens);
        self::assertSame(FinishReason::Stop, $response->finishReason);
    }

    public function test_parse_response_extracts_tool_calls(): void
    {
        $data = [
            'choices' => [['message' => [
                'content' => null,
                'tool_calls' => [['id' => 'call_9', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Conakry"}']]],
            ], 'finish_reason' => 'tool_calls']],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
        ];

        $response = $this->invoke('parseResponse', [$data]);

        self::assertTrue($response->hasToolCalls());
        self::assertSame('get_weather', $response->toolCalls[0]->name);
        self::assertSame(['city' => 'Conakry'], $response->toolCalls[0]->arguments);
    }
}
