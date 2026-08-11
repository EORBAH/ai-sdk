<?php

declare(strict_types=1);

namespace EorBah545\AI\Contracts;

use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\DTO\StreamChunk;
use EorBah545\AI\Exceptions\AiException;

/**
 * Contrat implémenté par chaque fournisseur (OpenAIProvider, AnthropicProvider,
 * XAIProvider, DeepSeekProvider, MistralProvider...). Un seul contrat pour
 * chat() et streaming — le reste (tool calling, structured output, cache de
 * prompt) passe par les champs de ChatRequest/Message, pas par des méthodes
 * séparées, pour que le code appelant reste identique d'un fournisseur à l'autre.
 */
interface ProviderInterface
{
    public function getName(): string;

    /**
     * @throws AiException
     */
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * @return \Generator<int,StreamChunk>
     * @throws AiException
     */
    public function stream(ChatRequest $request): \Generator;
}
