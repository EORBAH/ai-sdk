<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\OpenAI;

use EorBah545\AI\Contracts\FileClientInterface;
use EorBah545\AI\Exceptions\AiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Docs officielles : https://platform.openai.com/docs/api-reference/files
 */
final class OpenAIFileClient implements FileClientInterface
{
    private Client $http;

    public function __construct(private readonly string $apiKey, ?Client $httpClient = null)
    {
        $this->http = $httpClient ?? new Client(['base_uri' => 'https://api.openai.com/v1/', 'timeout' => 120]);
    }

    public function upload(string $filePath, string $purpose = 'batch'): string
    {
        try {
            $response = $this->http->post('files', [
                'headers' => ['Authorization' => 'Bearer ' . $this->apiKey],
                'multipart' => [
                    ['name' => 'purpose', 'contents' => $purpose],
                    ['name' => 'file', 'contents' => fopen($filePath, 'r'), 'filename' => basename($filePath)],
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new AiException("Échec de l'upload OpenAI : " . $e->getMessage(), [], $e);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];

        return $data['id'] ?? throw new AiException('Réponse OpenAI Files inattendue.', ['response' => $data]);
    }

    public function delete(string $fileId): bool
    {
        try {
            $response = $this->http->delete("files/{$fileId}", ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey]]);
        } catch (GuzzleException $e) {
            throw new AiException("Échec de la suppression OpenAI : " . $e->getMessage(), [], $e);
        }

        $data = json_decode((string) $response->getBody(), true) ?? [];

        return (bool) ($data['deleted'] ?? false);
    }

    public function content(string $fileId): string
    {
        try {
            $response = $this->http->get("files/{$fileId}/content", ['headers' => ['Authorization' => 'Bearer ' . $this->apiKey]]);
        } catch (GuzzleException $e) {
            throw new AiException("Échec de la récupération OpenAI : " . $e->getMessage(), [], $e);
        }

        return (string) $response->getBody();
    }
}
