<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\XAI;

use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use GuzzleHttp\Client;

/**
 * Provider xAI (Grok) — l'API xAI est annoncée compatible avec le format
 * OpenAI Chat Completions, on réutilise donc OpenAIProvider en ne changeant
 * que l'URL de base et les en-têtes.
 *
 * Docs officielles : https://docs.x.ai/docs/api-reference
 *
 * ⚠️ Certains paramètres avancés (ex: reasoning_effort, response_format
 * json_schema strict) peuvent différer légèrement d'OpenAI selon le modèle
 * Grok utilisé — vérifiez la doc xAI pour le modèle exact que vous ciblez.
 */
final class XAIProvider extends OpenAIProvider
{
    public function __construct(string $apiKey, ?Client $httpClient = null)
    {
        parent::__construct($apiKey, 'https://api.x.ai/v1', $httpClient);
    }

    public function getName(): string
    {
        return 'xai';
    }
}
