<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

use EorBah545\AI\Enum\FinishReason;

/**
 * Un fragment de réponse en streaming. La plupart des chunks ne portent
 * que $deltaText ; le dernier porte $done=true, $finishReason et $usage.
 */
final class StreamChunk
{
    public function __construct(
        public readonly string $deltaText = '',
        public readonly ?ToolCall $toolCall = null,
        public readonly bool $done = false,
        public readonly ?FinishReason $finishReason = null,
        public readonly ?Usage $usage = null,
    ) {
    }
}
