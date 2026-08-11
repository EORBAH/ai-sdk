<?php

declare(strict_types=1);

namespace EorBah545\AI;

use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\DTO\Message;

/**
 * Exécute les tool calls demandés par le modèle et produit les messages
 * "tool" (résultats) à renvoyer au fournisseur au tour suivant.
 *
 *   $runner = new ToolRunner($tools);
 *   $response = $provider->chat($request);
 *   if ($response->hasToolCalls()) {
 *       $resultMessages = $runner->run($response);
 *   }
 */
final class ToolRunner
{
    public function __construct(private readonly ToolRegistry $tools)
    {
    }

    /**
     * @return Message[]
     */
    public function run(ChatResponse $response): array
    {
        $results = [];

        foreach ($response->toolCalls as $call) {
            $tool = $this->tools->get($call->name);

            if ($tool === null) {
                $results[] = Message::toolResult($call->id, "Erreur : outil \"{$call->name}\" inconnu.", isError: true);

                continue;
            }

            try {
                $results[] = Message::toolResult($call->id, $tool->call($call->arguments));
            } catch (\Throwable $e) {
                $results[] = Message::toolResult($call->id, 'Erreur : ' . $e->getMessage(), isError: true);
            }
        }

        return $results;
    }
}
