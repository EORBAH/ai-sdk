<?php

/**
 * 05 — Structured output (JSON garanti)
 *
 * Nécessite OPENAI_API_KEY et/ou ANTHROPIC_API_KEY. Même schéma, même code
 * pour les deux fournisseurs — Anthropic n'a pas de mode natif, le SDK le
 * simule via un appel d'outil forcé (voir docs/05).
 *   php examples/05-structured-output.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Providers\Anthropic\AnthropicProvider;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Schema;

$schema = Schema::object([
    'nom' => Schema::string('Nom complet de la personne'),
    'age' => Schema::integer('Âge en années'),
    'pays' => Schema::string(enum: ['GN', 'FR', 'US']),
], required: ['nom', 'age', 'pays']);

function extraire(ProviderInterface $provider, string $model, array $schema, string $texte): void
{
    $response = $provider->chat(new ChatRequest(
        model: $model,
        messages: [Message::user($texte)],
        responseSchema: $schema,
    ));

    print_r($response->json());
}

$texte = 'Aissatou a 25 ans et vit en Guinée.';

if ($apiKey = getenv('OPENAI_API_KEY')) {
    extraire(new OpenAIProvider($apiKey), 'gpt-4.1', $schema, $texte);
}
if ($apiKey = getenv('ANTHROPIC_API_KEY')) {
    extraire(new AnthropicProvider($apiKey), 'claude-sonnet-4-6', $schema, $texte);
}
