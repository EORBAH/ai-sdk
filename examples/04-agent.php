<?php

/**
 * 04 — Agent (boucle automatique + réflexion)
 *
 * Nécessite OPENAI_API_KEY.
 *   php examples/04-agent.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\Agent;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use EorBah545\AI\ToolRegistry;

$provider = new OpenAIProvider(getenv('OPENAI_API_KEY'));

$tools = new ToolRegistry();
$tools->register(new Tool(
    name: 'get_weather',
    description: 'Renvoie la météo actuelle pour une ville.',
    parameters: Schema::object(['city' => Schema::string()], required: ['city']),
    handler: fn (array $args) => "Il fait 28°C et ensoleillé à {$args['city']}.",
));
$tools->register(new Tool(
    name: 'convert_celsius_to_fahrenheit',
    description: 'Convertit une température de Celsius vers Fahrenheit.',
    parameters: Schema::object(['celsius' => Schema::number()], required: ['celsius']),
    handler: fn (array $args) => (string) ($args['celsius'] * 9 / 5 + 32),
));

$agent = new Agent(
    provider: $provider,
    model: 'gpt-4.1',
    tools: $tools,
    systemPrompt: 'Tu es un assistant météo utile et concis.',
    reflect: true, // un tour de relecture critique avant la réponse finale
);

$response = $agent->run('Quelle température fait-il à Conakry, en Fahrenheit ?');
echo $response->text . "\n";
