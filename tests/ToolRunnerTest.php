<?php

declare(strict_types=1);

namespace EorBah545\AI\Tests;

use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\DTO\ToolCall;
use EorBah545\AI\DTO\Usage;
use EorBah545\AI\Enum\FinishReason;
use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use EorBah545\AI\ToolRegistry;
use EorBah545\AI\ToolRunner;
use PHPUnit\Framework\TestCase;

final class ToolRunnerTest extends TestCase
{
    private ToolRegistry $tools;

    protected function setUp(): void
    {
        $this->tools = new ToolRegistry();
        $this->tools->register(new Tool(
            name: 'get_weather',
            description: 'Météo',
            parameters: Schema::object(['city' => Schema::string()], required: ['city']),
            handler: fn (array $a) => "Il fait beau à {$a['city']}",
        ));
    }

    public function test_runs_matching_tool_and_returns_result_message(): void
    {
        $response = new ChatResponse(null, [new ToolCall('c1', 'get_weather', ['city' => 'Conakry'])], FinishReason::ToolCalls, new Usage());

        $results = (new ToolRunner($this->tools))->run($response);

        self::assertCount(1, $results);
        self::assertStringContainsString('Conakry', $results[0]->content[0]->content);
        self::assertFalse($results[0]->content[0]->isError);
    }

    public function test_unknown_tool_returns_error_message_instead_of_throwing(): void
    {
        $response = new ChatResponse(null, [new ToolCall('c2', 'inconnu', [])], FinishReason::ToolCalls, new Usage());

        $results = (new ToolRunner($this->tools))->run($response);

        self::assertTrue($results[0]->content[0]->isError);
    }

    public function test_handler_exception_is_caught_and_reported_as_tool_error(): void
    {
        $tools = new ToolRegistry();
        $tools->register(new Tool('boom', 'x', Schema::object([]), function () {
            throw new \RuntimeException('échec');
        }));
        $response = new ChatResponse(null, [new ToolCall('c3', 'boom', [])], FinishReason::ToolCalls, new Usage());

        $results = (new ToolRunner($tools))->run($response);

        self::assertTrue($results[0]->content[0]->isError);
        self::assertStringContainsString('échec', $results[0]->content[0]->content);
    }
}
