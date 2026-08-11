<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\Mistral;

use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use GuzzleHttp\Client;

/**
 * Provider Mistral (La Plateforme) — API très proche du format OpenAI Chat
 * Completions, on réutilise donc OpenAIProvider.
 *
 * Docs officielles : https://docs.mistral.ai/api/
 *
 * ⚠️ Le support exact de response_format (structured output) et de
 * reasoning_effort dépend du modèle Mistral utilisé — vérifiez la doc pour
 * le modèle ciblé avant de vous y fier en production.
 */
final class MistralProvider extends OpenAIProvider
{
    public function __construct(string $apiKey, ?Client $httpClient = null)
    {
        parent::__construct($apiKey, 'https://api.mistral.ai/v1', $httpClient);
    }

    public function getName(): string
    {
        return 'mistral';
    }
}
