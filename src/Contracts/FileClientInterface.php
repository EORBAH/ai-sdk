<?php

declare(strict_types=1);

namespace EorBah545\AI\Contracts;

/**
 * Contrat pour l'upload et la gestion de fichiers auprès d'un fournisseur
 * (utilisés pour le batch, la vision sur gros documents, le fine-tuning...).
 */
interface FileClientInterface
{
    /**
     * @return string L'id du fichier chez le fournisseur.
     */
    public function upload(string $filePath, string $purpose): string;

    public function delete(string $fileId): bool;

    public function content(string $fileId): string;
}
