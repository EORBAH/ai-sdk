<?php

declare(strict_types=1);

namespace EorBah545\AI;

/**
 * Un outil que le modèle peut décider d'appeler. $parameters est un schéma
 * JSON Schema (voir Schema::object()) décrivant les arguments attendus.
 * $handler reçoit les arguments déjà décodés et renvoie une chaîne (le
 * résultat, transmis tel quel au modèle au tour suivant).
 *
 *   new Tool(
 *       name: 'get_weather',
 *       description: 'Renvoie la météo actuelle pour une ville.',
 *       parameters: Schema::object(
 *           ['city' => Schema::string('Nom de la ville')],
 *           required: ['city'],
 *       ),
 *       handler: fn (array $args) => meteoApi($args['city']),
 *   );
 */
final class Tool
{
    /**
     * @param array<string,mixed> $parameters
     * @param callable(array<string,mixed>): string $handler
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly mixed $handler,
    ) {
    }

    public function call(array $arguments): string
    {
        return (string) ($this->handler)($arguments);
    }
}
