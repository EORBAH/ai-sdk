<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

/**
 * Représente un appel d'outil demandé par le modèle, à l'intérieur d'un
 * message assistant (après conversion de ToolCall en contenu de message
 * pour le renvoyer au fournisseur au tour suivant).
 */
final class ToolUsePart implements ContentPartInterface
{
    /**
     * @param array<string,mixed> $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
