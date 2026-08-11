<?php

declare(strict_types=1);

namespace EorBah545\AI\BuiltinTools;

use EorBah545\AI\Schema;
use EorBah545\AI\Tool;

/**
 * DANGER — exécute exactement la commande shell que le modèle décide de
 * lui donner. À utiliser UNIQUEMENT dans un environnement isolé et jetable
 * (conteneur sans accès réseau/données sensibles, VM sacrifiable...) —
 * jamais directement sur une machine de production ou avec des identifiants
 * réels accessibles. Un modèle mal aligné, mal prompté, ou manipulé par un
 * contenu malveillant dans ses entrées peut exécuter n'importe quoi.
 *
 *   $tools->register(BashTool::make(workingDirectory: '/sandbox', timeoutSeconds: 20));
 */
final class BashTool
{
    public static function make(?string $workingDirectory = null, int $timeoutSeconds = 30): Tool
    {
        return new Tool(
            name: 'bash',
            description: 'Exécute une commande shell et renvoie sa sortie standard et sa sortie d\'erreur.',
            parameters: Schema::object(
                ['command' => Schema::string('Commande shell à exécuter')],
                required: ['command'],
            ),
            handler: function (array $args) use ($workingDirectory, $timeoutSeconds): string {
                $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $process = proc_open($args['command'], $descriptors, $pipes, $workingDirectory);

                if (!is_resource($process)) {
                    return 'Erreur : impossible de démarrer le processus.';
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

                return trim($stdout . ($stderr !== '' ? "\n[stderr]\n{$stderr}" : ''));
            },
        );
    }
}
