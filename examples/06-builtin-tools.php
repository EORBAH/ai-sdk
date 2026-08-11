<?php

/**
 * 06 — Outils intégrés (web_search, bash, éditeur de texte, exécution de code)
 *
 * ⚠️ BashTool et CodeExecutionTool exécutent ce que le modèle leur donne —
 * lisez docs/06-outils-integres.md avant d'exécuter cet exemple ailleurs
 * que dans un environnement jetable/isolé.
 *
 * Nécessite OPENAI_API_KEY, GOOGLE_API_KEY, GOOGLE_CX (recherche web).
 *   php examples/06-builtin-tools.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\Agent;
use EorBah545\AI\BuiltinTools\BashTool;
use EorBah545\AI\BuiltinTools\CodeExecutionTool;
use EorBah545\AI\BuiltinTools\TextEditorTool;
use EorBah545\AI\BuiltinTools\WebSearchTool;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\ToolRegistry;

$sandbox = sys_get_temp_dir() . '/ai_sdk_sandbox_' . uniqid();
mkdir($sandbox);

$tools = new ToolRegistry();

if (getenv('GOOGLE_API_KEY') && getenv('GOOGLE_CX')) {
    $tools->register(WebSearchTool::make(getenv('GOOGLE_API_KEY'), getenv('GOOGLE_CX')));
}
$tools->register(BashTool::make($sandbox, timeoutSeconds: 20));
$tools->register(TextEditorTool::make($sandbox));
$tools->register(CodeExecutionTool::make(['python'], timeoutSeconds: 15));

$agent = new Agent(
    provider: new OpenAIProvider(getenv('OPENAI_API_KEY')),
    model: 'gpt-4.1',
    tools: $tools,
    systemPrompt: "Tu es un agent de développement. Ton dossier de travail est {$sandbox}.",
);

$response = $agent->run(
    'Crée un script Python qui calcule les 10 premiers nombres premiers, exécute-le, ' .
    'sauvegarde le résultat dans un fichier resultat.txt, puis confirme son contenu.'
);

echo $response->text . "\n";
echo "Dossier de travail : {$sandbox}\n";
