<?php

/**
 * 01 — Chat simple
 *
 * Nécessite OPENAI_API_KEY et/ou ANTHROPIC_API_KEY dans l'environnement.
 *   php examples/01-chat-simple.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Providers\Anthropic\AnthropicProvider;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;

function demander(ProviderInterface $provider, string $model, string $question): void
{
    $response = $provider->chat(new ChatRequest($model, [Message::user($question)]));

    echo "[{$provider->getName()}] {$response->text}\n";
    echo "  tokens entrée={$response->usage->inputTokens} sortie={$response->usage->outputTokens}\n\n";
}

if ($apiKey = getenv('OPENAI_API_KEY')) {
    demander(new OpenAIProvider($apiKey), 'gpt-4.1', 'Explique le tool calling en une phrase.');
}

if ($apiKey = getenv('ANTHROPIC_API_KEY')) {
    // Même fonction demander(), aucune modification pour changer de fournisseur.
    demander(new AnthropicProvider($apiKey), 'claude-sonnet-4-6', 'Explique le tool calling en une phrase.');
}
