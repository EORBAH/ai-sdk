<?php

declare(strict_types=1);

namespace EorBah545\AI\Tests;

use EorBah545\AI\Agent;
use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\DTO\ToolCall;
use EorBah545\AI\DTO\Usage;
use EorBah545\AI\Enum\FinishReason;
use EorBah545\AI\Exceptions\AiException;
use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use EorBah545\AI\ToolRegistry;
use EorBah545\PHPUnit\Framework\TestCase;

final class AgentTest extends TestCase
{
    private function tools(): ToolRegistry
    {
        $tools = new ToolRegistry();
        $tools->register(new Tool('get_weather', 'Météo', Schema::object(['city' => Schema::string()], ['city']), fn ($a) => 'beau'));

        return $tools;
    }

    public function test_runs_the_tool_loop_until_final_answer(): void
    {
        $provider = new class implements ProviderInterface {
            public int $calls = 0;
            public function getName(): string { return 'fake'; }
            public function chat(ChatRequest $request): ChatResponse
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return new ChatResponse(null, [new ToolCall('c1', 'get_weather', ['city' => 'Conakry'])], FinishReason::ToolCalls, new Usage());
                }
                return new ChatResponse('Il fait beau à Conakry.', [], FinishReason::Stop, new Usage());
            }
            public function stream(ChatRequest $request): \Generator { yield; }
        };

        $agent = new Agent($provider, 'fake-model', $this->tools());
        $result = $agent->run('Météo à Conakry ?');

        self::assertSame(2, $provider->calls);
        self::assertStringContainsString('Conakry', $result->text);
    }

    public function test_throws_when_tool_requested_without_registry(): void
    {
        $provider = new class implements ProviderInterface {
            public function getName(): string { return 'fake'; }
            public function chat(ChatRequest $request): ChatResponse
            {
                return new ChatResponse(null, [new ToolCall('c', 'x', [])], FinishReason::ToolCalls, new Usage());
            }
            public function stream(ChatRequest $request): \Generator { yield; }
        };

        $agent = new Agent($provider, 'fake-model'); // pas de ToolRegistry

        $this->expectException(AiException::class);
        $agent->run('test');
    }

    public function test_throws_when_max_iterations_reached(): void
    {
        $provider = new class implements ProviderInterface {
            public function getName(): string { return 'fake'; }
            public function chat(ChatRequest $request): ChatResponse
            {
                return new ChatResponse(null, [new ToolCall('c', 'get_weather', ['city' => 'x'])], FinishReason::ToolCalls, new Usage());
            }
            public function stream(ChatRequest $request): \Generator { yield; }
        };

        $agent = new Agent($provider, 'fake-model', $this->tools(), maxIterations: 3);

        $this->expectException(AiException::class);
        $this->expectExceptionMessageMatches('/itérations/');
        $agent->run('test');
    }
}
