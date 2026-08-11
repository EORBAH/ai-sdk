<?php

declare(strict_types=1);

namespace EorBah545\AI;

/**
 * Petits raccourcis pour construire un JSON Schema — utilisé à la fois pour
 * les paramètres d'un Tool et pour le structured output (ChatRequest::$responseSchema).
 *
 *   Schema::object([
 *       'nom' => Schema::string('Nom complet'),
 *       'age' => Schema::integer('Âge en années'),
 *       'pays' => Schema::string(enum: ['GN', 'FR', 'US']),
 *   ], required: ['nom', 'age']);
 */
final class Schema
{
    /**
     * @param array<string,mixed> $properties
     * @param string[] $required
     */
    public static function object(array $properties, array $required = [], bool $additionalProperties = false): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => $additionalProperties,
        ];
    }

    /**
     * @param string[]|null $enum
     */
    public static function string(?string $description = null, ?array $enum = null): array
    {
        return array_filter([
            'type' => 'string',
            'description' => $description,
            'enum' => $enum,
        ], static fn ($v) => $v !== null);
    }

    public static function integer(?string $description = null): array
    {
        return array_filter(['type' => 'integer', 'description' => $description], static fn ($v) => $v !== null);
    }

    public static function number(?string $description = null): array
    {
        return array_filter(['type' => 'number', 'description' => $description], static fn ($v) => $v !== null);
    }

    public static function boolean(?string $description = null): array
    {
        return array_filter(['type' => 'boolean', 'description' => $description], static fn ($v) => $v !== null);
    }

    /**
     * @param array<string,mixed> $items Schéma des éléments du tableau.
     */
    public static function array(array $items, ?string $description = null): array
    {
        return array_filter([
            'type' => 'array',
            'items' => $items,
            'description' => $description,
        ], static fn ($v) => $v !== null);
    }
}
