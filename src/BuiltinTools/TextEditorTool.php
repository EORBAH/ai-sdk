<?php

declare(strict_types=1);

namespace EorBah545\AI\BuiltinTools;

use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use RuntimeException;

/**
 * Outil "éditeur de texte" façon Anthropic text_editor / OpenAI file tools :
 * view (lire), create (créer/écraser), str_replace (remplacer un extrait).
 * Toutes les opérations sont restreintes à $baseDirectory — toute tentative
 * de sortir du dossier (ex: "../../etc/passwd") est refusée.
 *
 *   $tools->register(TextEditorTool::make('/sandbox/projet'));
 */
final class TextEditorTool
{
    public static function make(string $baseDirectory): Tool
    {
        $base = rtrim(realpath($baseDirectory) ?: $baseDirectory, '/');

        $resolve = function (string $path) use ($base): string {
            $target = $base . '/' . ltrim($path, '/');
            $real = realpath(dirname($target));
            if ($real === false || !str_starts_with($real, $base)) {
                throw new RuntimeException("Chemin refusé (hors de {$base}) : {$path}");
            }

            return $target;
        };

        return new Tool(
            name: 'text_editor',
            description: 'Lit, crée ou modifie un fichier texte. command="view" lit un fichier, ' .
                '"create" crée/écrase un fichier avec "content", "str_replace" remplace "old_str" par "new_str".',
            parameters: Schema::object([
                'command' => Schema::string(enum: ['view', 'create', 'str_replace']),
                'path' => Schema::string('Chemin relatif au dossier de travail'),
                'content' => Schema::string('Contenu complet (pour create)'),
                'old_str' => Schema::string('Texte exact à remplacer (pour str_replace)'),
                'new_str' => Schema::string('Texte de remplacement (pour str_replace)'),
            ], required: ['command', 'path']),
            handler: function (array $args) use ($resolve): string {
                $path = $resolve($args['path']);

                return match ($args['command']) {
                    'view' => is_file($path) ? file_get_contents($path) : "Erreur : fichier introuvable ({$args['path']}).",
                    'create' => (static function () use ($path, $args): string {
                        file_put_contents($path, $args['content'] ?? '');

                        return "Fichier écrit : {$args['path']}";
                    })(),
                    'str_replace' => (static function () use ($path, $args): string {
                        if (!is_file($path)) {
                            return "Erreur : fichier introuvable ({$args['path']}).";
                        }
                        $content = file_get_contents($path);
                        $count = substr_count($content, $args['old_str']);
                        if ($count !== 1) {
                            return "Erreur : old_str doit apparaître exactement une fois (trouvé {$count} fois).";
                        }
                        file_put_contents($path, str_replace($args['old_str'], $args['new_str'], $content));

                        return "Fichier modifié : {$args['path']}";
                    })(),
                    default => 'Erreur : commande inconnue.',
                };
            },
        );
    }
}
