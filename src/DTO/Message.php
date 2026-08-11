<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

use AI\Enum\Role;

/**
 * Un message de conversation. $content est soit une simple chaîne (cas
 * courant : texte utilisateur/assistant), soit un tableau de blocs
 * (TextPart, ImagePart, ToolUsePart, ToolResultPart) pour le multimodal
 * ou le tool calling.
 */
final class Message
{
    /**
     * @param array<int,ContentPartInterface>|string $content
     */
    public function __construct(
        public readonly Role $role,
        public readonly array|string $content,
    ) {
    }

    public static function system(string $text, bool $cache = false): self
    {
        return new self(Role::System, $cache ? [new TextPart($text, true)] : $text);
    }

    public static function user(string $text): self
    {
        return new self(Role::User, $text);
    }

    /**
     * @param array<int,ImagePart> $images
     */
    public static function userWithImages(string $text, array $images): self
    {
        return new self(Role::User, [new TextPart($text), ...$images]);
    }

    public static function assistant(string $text): self
    {
        return new self(Role::Assistant, $text);
    }

    /**
     * @param ToolCall[] $toolCalls
     */
    public static function assistantToolCalls(array $toolCalls): self
    {
        return new self(Role::Assistant, array_map(
            static fn (ToolCall $c) => new ToolUsePart($c->id, $c->name, $c->arguments),
            $toolCalls
        ));
    }

    public static function toolResult(string $toolUseId, string $content, bool $isError = false): self
    {
        return new self(Role::Tool, [new ToolResultPart($toolUseId, $content, $isError)]);
    }
}
