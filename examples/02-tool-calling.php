<?php

/**
 * 02 — Tool calling (boucle manuelle)
 *
 * Nécessite OPENAI_API_KEY.
 *   php examples/02-tool-calling.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use EorBah545\AI\ToolRegistry;
use EorBah545\AI\ToolRunner;

$provider = new OpenAIProvider(getenv('OPENAI_API_KEY'));

$tools = new ToolRegistry();
$tools->register(new Tool(
    name: 'get_weather',
    description: 'Renvoie la météo actuelle pour une ville.',
    parameters: Schema::object(['city' => Schema::string('Nom de la ville')], required: ['city']),
    handler: fn (array $args) => "Il fait 28°C et ensoleillé à {$args['city']}.",
));

$messages = [Message::user('Quelle est la météo à Conakry ?')];
$runner = new ToolRunner($tools);

while (true) {
    $response = $provider->chat(new ChatRequest('gpt-4.1', $messages, $tools));

    if (!$response->hasToolCalls()) {
        echo $response->text . "\n";
        break;
    }

    echo "[le modèle demande l'outil {$response->toolCalls[0]->name}]\n";

    $messages[] = Message::assistantToolCalls($response->toolCalls);
    foreach ($runner->run($response) as $toolResultMessage) {
        $messages[] = $toolResultMessage;
    }
}
