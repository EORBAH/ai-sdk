<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

use EorBah545\AI\ToolRegistry;

/**
 * Requête normalisée envoyée à n'importe quel ProviderInterface.
 */
final class ChatRequest
{
    /**
     * @param Message[] $messages
     * @param array<string,mixed>|null $responseSchema JSON Schema pour le
     *        structured output (voir AI\Schema). Le fournisseur garantit
     *        que $response->text est un JSON valide respectant ce schéma.
     * @param string|null $reasoningEffort 'low'|'medium'|'high' — transmis
     *        tel quel aux fournisseurs qui exposent un mode "raisonnement"
     *        (o-series OpenAI, Claude extended thinking...). Ignoré sinon.
     */
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly ?ToolRegistry $tools = null,
        public readonly ?array $responseSchema = null,
        public readonly float $temperature = 1.0,
        public readonly ?int $maxTokens = null,
        public readonly ?string $reasoningEffort = null,
    ) {
    }
}
