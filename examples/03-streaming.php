<?php

/**
 * 03 — Streaming
 *
 * Nécessite OPENAI_API_KEY.
 *   php examples/03-streaming.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;

$provider = new OpenAIProvider(getenv('OPENAI_API_KEY'));

$request = new ChatRequest('gpt-4.1', [Message::user('Écris un court poème sur la Guinée, en 4 lignes.')]);

foreach ($provider->stream($request) as $chunk) {
    if ($chunk->deltaText !== '') {
        echo $chunk->deltaText;
        flush();
    }
    if ($chunk->done) {
        echo "\n\n[terminé — {$chunk->usage->outputTokens} tokens générés, raison : {$chunk->finishReason->value}]\n";
    }
}
