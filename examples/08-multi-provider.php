<?php

/**
 * 08 — Multi-provider : la même tâche sur plusieurs fournisseurs
 *
 * Montre l'intérêt central du SDK : changer de fournisseur = changer une
 * ligne, le reste du code (Agent, ToolRegistry, boucle) ne bouge pas.
 *
 * Nécessite les clés API des fournisseurs que vous voulez comparer.
 *   php examples/08-multi-provider.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Providers\Anthropic\AnthropicProvider;
use EorBah545\AI\Providers\DeepSeek\DeepSeekProvider;
use EorBah545\AI\Providers\Mistral\MistralProvider;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Providers\XAI\XAIProvider;

/** @var array<string,array{provider: ProviderInterface, model: string}> $candidats */
$candidats = [];

if ($k = getenv('OPENAI_API_KEY')) {
    $candidats['openai'] = ['provider' => new OpenAIProvider($k), 'model' => 'gpt-4.1'];
}
if ($k = getenv('ANTHROPIC_API_KEY')) {
    $candidats['anthropic'] = ['provider' => new AnthropicProvider($k), 'model' => 'claude-sonnet-4-6'];
}
if ($k = getenv('XAI_API_KEY')) {
    $candidats['xai'] = ['provider' => new XAIProvider($k), 'model' => 'grok-4'];
}
if ($k = getenv('DEEPSEEK_API_KEY')) {
    $candidats['deepseek'] = ['provider' => new DeepSeekProvider($k), 'model' => 'deepseek-chat'];
}
if ($k = getenv('MISTRAL_API_KEY')) {
    $candidats['mistral'] = ['provider' => new MistralProvider($k), 'model' => 'mistral-large-latest'];
}

$question = 'En une phrase : pourquoi le ciel est-il bleu ?';

foreach ($candidats as $nom => $c) {
    $response = $c['provider']->chat(new ChatRequest($c['model'], [Message::user($question)]));
    echo "[{$nom}] {$response->text}\n";
}
