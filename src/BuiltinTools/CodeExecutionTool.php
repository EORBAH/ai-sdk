<?php

declare(strict_types=1);

namespace EorBah545\AI\BuiltinTools;

use EorBah545\AI\Schema;
use EorBah545\AI\Tool;

/**
 * ⚠️ Même avertissement que BashTool : exécute du code fourni par le modèle
 * dans un sous-processus. À réserver à un environnement isolé et jetable.
 *
 * Contrairement à BashTool (une commande arbitraire), cet outil exécute un
 * extrait de code dans un langage précis (php ou python par défaut) via un
 * fichier temporaire — pratique pour les tâches de calcul/données que le
 * modèle préfère exprimer en code plutôt qu'en commande shell.
 *
 *   $tools->register(CodeExecutionTool::make(['php', 'python'], timeoutSeconds: 15));
 */
final class CodeExecutionTool
{
    private const RUNNERS = [
        'php' => ['bin' => 'php', 'ext' => '.php'],
        'python' => ['bin' => 'python3', 'ext' => '.py'],
    ];

    /**
     * @param string[] $allowedLanguages Sous-ensemble de ['php', 'python'].
     */
    public static function make(array $allowedLanguages = ['php'], int $timeoutSeconds = 15): Tool
    {
        return new Tool(
            name: 'code_execution',
            description: 'Exécute un extrait de code (' . implode(' ou ', $allowedLanguages) . ') et renvoie sa sortie standard.',
            parameters: Schema::object([
                'language' => Schema::string(enum: $allowedLanguages),
                'code' => Schema::string('Code source à exécuter'),
            ], required: ['language', 'code']),
            handler: function (array $args) use ($allowedLanguages, $timeoutSeconds): string {
                $language = $args['language'];
                if (!in_array($language, $allowedLanguages, true) || !isset(self::RUNNERS[$language])) {
                    return "Erreur : langage non autorisé ({$language}).";
                }

                $runner = self::RUNNERS[$language];
                $file = tempnam(sys_get_temp_dir(), 'ai_code_') . $runner['ext'];
                file_put_contents($file, $args['code']);

                $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $process = proc_open([$runner['bin'], $file], $descriptors, $pipes);

                if (!is_resource($process)) {
                    unlink($file);

                    return "Erreur : impossible d'exécuter {$runner['bin']}.";
                }

                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);

                $stdout = '';
                $stderr = '';
                $start = microtime(true);

                while (true) {
                    $status = proc_get_status($process);
                    $stdout .= stream_get_contents($pipes[1]);
                    $stderr .= stream_get_contents($pipes[2]);

                    if (!$status['running']) {
                        break;
                    }
                    if (microtime(true) - $start > $timeoutSeconds) {
                        proc_terminate($process);
                        $stderr .= "\n[Interrompu après {$timeoutSeconds}s]";
                        break;
                    }
                    usleep(20_000);
                }

                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                unlink($file);

                return trim($stdout . ($stderr !== '' ? "\n[stderr]\n{$stderr}" : ''));
            },
        );
    }
}
