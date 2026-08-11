<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

/**
 * Appel d'outil décodé et normalisé, quel que soit le fournisseur.
 */
final class ToolCall
{
    /**
     * @param array<string,mixed> $arguments Arguments déjà décodés (JSON → array).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
