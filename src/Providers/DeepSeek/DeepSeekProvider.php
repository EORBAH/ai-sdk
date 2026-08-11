<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\DeepSeek;

use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use GuzzleHttp\Client;

/**
 * Provider DeepSeek — API officiellement compatible avec le format OpenAI
 * Chat Completions, on réutilise donc OpenAIProvider.
 *
 * Docs officielles : https://api-docs.deepseek.com
 *
 * ⚠️ deepseek-reasoner expose un raisonnement visible différemment (champ
 * reasoning_content dans la réponse, non repris tel quel par ce provider) —
 * si vous avez besoin de la trace de raisonnement brute, lisez $response->raw.
 */
final class DeepSeekProvider extends OpenAIProvider
{
    public function __construct(string $apiKey, ?Client $httpClient = null)
    {
        parent::__construct($apiKey, 'https://api.deepseek.com/v1', $httpClient);
    }

    public function getName(): string
    {
        return 'deepseek';
    }
}
