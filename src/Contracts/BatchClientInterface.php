<?php

declare(strict_types=1);

namespace EorBah545\AI\Contracts;

/**
 * Contrat pour soumettre un lot de requêtes de chat en traitement différé
 * (moins cher, résultats sous quelques heures — utile pour de gros volumes
 * non urgents).
 */
interface BatchClientInterface
{
    /**
     * @param array<int,array{custom_id: string, request: \AI\DTO\ChatRequest}> $requests
     * @return string L'id du batch chez le fournisseur.
     */
    public function submit(array $requests): string;

    /**
     * @return array{status: string, completed: int, total: int}
     */
    public function status(string $batchId): array;

    /**
     * @return array<string,\AI\DTO\ChatResponse> Résultats indexés par custom_id.
     */
    public function results(string $batchId): array;
}
