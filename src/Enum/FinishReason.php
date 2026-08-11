<?php

declare(strict_types=1);

namespace EorBah545\AI\Enum;

/**
 * Raison normalisée de fin de génération, quel que soit le fournisseur
 * (OpenAI "finish_reason", Anthropic "stop_reason"...).
 */
enum FinishReason: string
{
    case Stop = 'stop';
    case ToolCalls = 'tool_calls';
    case Length = 'length';
    case ContentFilter = 'content_filter';
    case Error = 'error';
}
