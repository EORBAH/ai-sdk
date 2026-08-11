<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

use EorBah545\AI\Enum\FinishReason;

final class ChatResponse
{
    /**
     * @param ToolCall[] $toolCalls
     * @param array<string,mixed> $raw Réponse brute du fournisseur (debug/traçabilité).
     */
    public function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls,
        public readonly FinishReason $finishReason,
        public readonly Usage $usage,
        public readonly array $raw = [],
    ) {
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    /**
     * Décode $text comme JSON — pratique après un appel avec responseSchema
     * (structured output).
     *
     * @return array<string,mixed>
     */
    public function json(): array
    {
        return json_decode($this->text ?? '', true, flags: JSON_THROW_ON_ERROR);
    }
}
