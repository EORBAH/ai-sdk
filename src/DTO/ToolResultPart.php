<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

final class ToolResultPart implements ContentPartInterface
{
    public function __construct(
        public readonly string $toolUseId,
        public readonly string $content,
        public readonly bool $isError = false,
    ) {
    }
}
