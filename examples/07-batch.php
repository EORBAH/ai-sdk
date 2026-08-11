<?php

/**
 * 07 — Batch (traitement différé, moins cher)
 *
 * Nécessite OPENAI_API_KEY. Le batch met généralement plusieurs minutes à
 * plusieurs heures à se terminer — ce script montre juste la soumission et
 * une vérification de statut immédiate (probablement "in_progress").
 *   php examples/07-batch.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Providers\OpenAI\OpenAIBatchClient;

$batch = new OpenAIBatchClient(getenv('OPENAI_API_KEY'));

$batchId = $batch->submit([
    ['custom_id' => 'resume-1', 'request' => new ChatRequest('gpt-4.1', [Message::user('Résume en une phrase : la photosynthèse.')])],
    ['custom_id' => 'resume-2', 'request' => new ChatRequest('gpt-4.1', [Message::user('Résume en une phrase : la relativité restreinte.')])],
]);

echo "Batch soumis : {$batchId}\n";

$statut = $batch->status($batchId);
echo "Statut : {$statut['status']} ({$statut['completed']}/{$statut['total']})\n";
echo "Repassez plus tard avec \$batch->results('{$batchId}') une fois status()=='completed'.\n";
