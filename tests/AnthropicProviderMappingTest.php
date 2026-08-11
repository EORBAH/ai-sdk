<?php

declare(strict_types=1);

namespace EorBah545\AI\Tests;

use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\DTO\ToolCall;
use EorBah545\AI\Enum\FinishReason;
use EorBah545\AI\Providers\Anthropic\AnthropicProvider;
use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use EorBah545\AI\ToolRegistry;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AnthropicProviderMappingTest extends TestCase
{
    private AnthropicProvider $provider;

    protected function setUp(): void
    {
        $this->provider = (new ReflectionClass(AnthropicProvider::class))->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, array $args): mixed
    {
        $m = new ReflectionMethod($this->provider, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->provider, $args);
    }

    public function test_system_prompt_is_separated_and_supports_cache_control(): void
    {
        $request = new ChatRequest(
            model: 'claude-sonnet-4-6',
            messages: [Message::system('Tu es utile.', cache: true), Message::user('Météo ?')],
            maxTokens: 1024,
        );

        $payload = $this->invoke('buildPayload', [$request, false]);

        self::assertSame(1024, $payload['max_tokens']);
        self::assertSame('Tu es utile.', $payload['system'][0]['text']);
        self::assertSame(['type' => 'ephemeral'], $payload['system'][0]['cache_control']);
        self::assertCount(1, $payload['messages']); // le system n'apparaît pas dans messages
    }

    public function test_tool_result_is_sent_as_user_role(): void
    {
        $request = new ChatRequest('claude-sonnet-4-6', [
            Message::user('Météo ?'),
            Message::assistantToolCalls([new ToolCall('call_1', 'get_weather', ['city' => 'Conakry'])]),
            Message::toolResult('call_1', 'Il fait beau'),
        ]);

        $payload = $this->invoke('buildPayload', [$request, false]);
        $messages = $payload['messages'];

        self::assertSame('assistant', $messages[1]['role']);
        self::assertSame('tool_use', $messages[1]['content'][0]['type']);
        self::assertSame(['city' => 'Conakry'], $messages[1]['content'][0]['input']);

        self::assertSame('user', $messages[2]['role'], 'Anthropic n\'a pas de rôle "tool" : le résultat passe côté user');
        self::assertSame('tool_result', $messages[2]['content'][0]['type']);
        self::assertSame('call_1', $messages[2]['content'][0]['tool_use_id']);
    }

    public function test_structured_output_forces_a_tool_choice(): void
    {
        $request = new ChatRequest('claude-sonnet-4-6', [Message::user('donne un json')], responseSchema: Schema::object(['x' => Schema::string()], ['x']));

        $payload = $this->invoke('buildPayload', [$request, false]);

        self::assertSame('tool', $payload['tool_choice']['type']);
        $lastTool = end($payload['tools']);
        self::assertSame('string', $lastTool['input_schema']['properties']['x']['type']);
    }

    public function test_parse_response_extracts_text_and_cached_tokens(): void
    {
        $data = [
            'content' => [['type' => 'text', 'text' => 'Il fait beau.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 15, 'output_tokens' => 6, 'cache_read_input_tokens' => 3],
        ];

        $response = $this->invoke('parseResponse', [$data]);

        self::assertSame('Il fait beau.', $response->text);
        self::assertSame(3, $response->usage->cachedInputTokens);
        self::assertSame(FinishReason::Stop, $response->finishReason);
    }

    public function test_parse_response_extracts_tool_use(): void
    {
        $data = [
            'content' => [['type' => 'tool_use', 'id' => 'call_5', 'name' => 'get_weather', 'input' => ['city' => 'Conakry']]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 4],
        ];

        $response = $this->invoke('parseResponse', [$data]);

        self::assertTrue($response->hasToolCalls());
        self::assertSame(['city' => 'Conakry'], $response->toolCalls[0]->arguments);
    }
}
