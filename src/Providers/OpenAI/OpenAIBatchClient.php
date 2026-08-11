<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\OpenAI;

use EorBah545\AI\Contracts\BatchClientInterface;
use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\Exceptions\AiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Docs officielles : https://platform.openai.com/docs/guides/batch
 *
 * Flux : construit un fichier JSONL (une ligne = une requête chat, avec un
 * custom_id fourni par l'appelant), l'upload, crée le batch, et permet de
 * récupérer le statut puis les résultats une fois complet — généralement
 * sous 24h, à un tarif réduit par rapport aux appels synchrones.
 */
final class OpenAIBatchClient implements BatchClientInterface
{
    private Client $http;
    private OpenAIProvider $provider;
    private OpenAIFileClient $files;

    public function __construct(private readonly string $apiKey, ?Client $httpClient = null)
    {
        $this->http = $httpClient ?? new Client(['base_uri' => 'https://api.openai.com/v1/', 'timeout' => 60]);
        $this->provider = new OpenAIProvider($apiKey, httpClient: $this->http);
        $this->files = new OpenAIFileClient($apiKey, $this->http);
    }

    public function submit(array $requests): string
    {
        $lines = [];
        foreach ($requests as $item) {
            $lines[] = json_encode([
                'custom_id' => $item['custom_id'],
                'method' => 'POST',
                'url' => '/v1/chat/completions',
                'body' => $this->provider->toRequestPayload($item['request']),
            ]);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'ai_batch_') . '.jsonl';
        file_put_contents($tmpFile, implode("\n", $lines));

        $fileId = $this->files->upload($tmpFile, 'batch');
        unlink($tmpFile);

        try {
            $response = $this->http->post('batches', [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'],
                'json' => ['input_file_id' => $fileId, 'endpoint' => '/v1/chat/completions', 'completion_window' => '24h'],
            ]);
        } catch (GuzzleException $e) {
            throw new AiException('Échec de la création du batch OpenAI : ' . $e->getMessage(), [], $e);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];

        return $data['id'] ?? throw new AiException('Réponse OpenAI Batch inattendue.', ['response' => $data]);
    }

    public function status(string $batchId): array
    {
        $data = $this->fetchBatch($batchId);

        return [
            'status' => $data['status'] ?? 'unknown',
            'completed' => $data['request_counts']['completed'] ?? 0,
            'total' => $data['request_counts']['total'] ?? 0,
        ];
    }

    public function results(string $batchId): array
    {
        $data = $this->fetchBatch($batchId);

        if (($data['status'] ?? null) !== 'completed' || !isset($data['output_file_id'])) {
            throw new AiException("Le batch {$batchId} n'est pas encore terminé (statut : " . ($data['status'] ?? 'inconnu') . ').');
        }

        $content = $this->files->content($data['output_file_id']);
        $results = [];

        foreach (explode("\n", trim($content)) as $line) {
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            $results[$row['custom_id']] = $this->provider->parseChatResponse($row['response']['body'] ?? []);
        }

        return $results;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchBatch(string $batchId): array
    {
        try {
            $response = $this->http->get("batches/{$batchId}", ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey]]);
        } catch (GuzzleException $e) {
            throw new AiException('Échec de la lecture du batch OpenAI : ' . $e->getMessage(), [], $e);
        }

        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
