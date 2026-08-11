<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

final class TextPart implements ContentPartInterface
{
    /**
     * @param bool $cache Marque ce bloc comme éligible au cache de prompt
     *                     (utilisé par AnthropicProvider — cache_control).
     *                     Sans effet chez les fournisseurs qui cachent
     *                     automatiquement (OpenAI).
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $cache = false,
    ) {
    }
}
