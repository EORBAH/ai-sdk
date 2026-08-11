<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

use EorBah545\AI\Agent;
use EorBah545\AI\BuiltinTools\BashTool;
use EorBah545\AI\BuiltinTools\CodeExecutionTool;
use EorBah545\AI\BuiltinTools\TextEditorTool;
use EorBah545\AI\BuiltinTools\WebSearchTool;
use EorBah545\AI\Providers\Mistral\MistralProvider;
use EorBah545\AI\ToolRegistry;

$sandbox = sys_get_temp_dir() . '/ai_sdk_sandbox_' . uniqid();
$outputZip = __DIR__ . '/examples/package.zip';
mkdir($sandbox);

$tools = new ToolRegistry();

if ($_ENV['GOOGLE_API_KEY'] && $_ENV['GOOGLE_CX']) {
    $tools->register(WebSearchTool::make($_ENV['GOOGLE_API_KEY'], $_ENV['GOOGLE_CX']));
}

$tools->register(BashTool::make($sandbox, timeoutSeconds: 20));
$tools->register(TextEditorTool::make($sandbox));
$tools->register(CodeExecutionTool::make(['python'], timeoutSeconds: 15));

$agent = new Agent(
    provider: new MistralProvider($_ENV['MISTRAL_API_KEY']),
    model: 'codestral-latest',
    tools: $tools,
    systemPrompt: "Tu es un agent de développement. Ton dossier de travail est {$sandbox}.",
);

$response = $agent->run(
    'Je veut que tu me créer un packages php ensuite que tu compresse en .zip et tu copie' .
    "le dossier vers {$outputZip}." .
    "Il faut que ce packages soit un mini fastapi tu as accès a composer install eor_bah545/asyncio et" .
    "concois moi une apis avec des routes une classe FastAPI, Request, Response, des function comme JSONResponse et avec un support sur les return [] et \$request->headers[]"
);

echo $response->text . "\n";
echo "Dossier de travail : {$sandbox}\n";
