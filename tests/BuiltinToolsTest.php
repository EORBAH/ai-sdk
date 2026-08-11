<?php

declare(strict_types=1);

namespace AI\Tests;

use EorBah545\AI\BuiltinTools\BashTool;
use EorBah545\AI\BuiltinTools\CodeExecutionTool;
use EorBah545\AI\BuiltinTools\TextEditorTool;
use PHPUnit\Framework\TestCase;

final class BuiltinToolsTest extends TestCase
{
    public function test_bash_tool_executes_a_real_command(): void
    {
        $bash = BashTool::make(timeoutSeconds: 5);

        self::assertSame('bonjour', trim($bash->call(['command' => 'echo bonjour'])));
    }

    public function test_code_execution_tool_runs_real_php_code(): void
    {
        $tool = CodeExecutionTool::make(['php'], timeoutSeconds: 5);

        self::assertSame('42', trim($tool->call(['language' => 'php', 'code' => '<?php echo 21 * 2;'])));
    }

    public function test_text_editor_tool_create_view_and_str_replace(): void
    {
        $dir = sys_get_temp_dir() . '/ai_editor_test_' . uniqid();
        mkdir($dir);
        $editor = TextEditorTool::make($dir);

        $editor->call(['command' => 'create', 'path' => 'test.txt', 'content' => "Bonjour\nMonde"]);
        self::assertSame("Bonjour\nMonde", $editor->call(['command' => 'view', 'path' => 'test.txt']));

        $editor->call(['command' => 'str_replace', 'path' => 'test.txt', 'old_str' => 'Monde', 'new_str' => 'PHP']);
        self::assertSame("Bonjour\nPHP", $editor->call(['command' => 'view', 'path' => 'test.txt']));
    }

    public function test_text_editor_tool_rejects_path_traversal(): void
    {
        $dir = sys_get_temp_dir() . '/ai_editor_test_' . uniqid();
        mkdir($dir);
        $editor = TextEditorTool::make($dir);

        $this->expectException(\RuntimeException::class);
        $editor->call(['command' => 'view', 'path' => '../../../etc/passwd']);
    }
}
