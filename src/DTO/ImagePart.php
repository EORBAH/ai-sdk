<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

use InvalidArgumentException;

/**
 * Image transmise soit par URL, soit en base64 (fournissez l'un OU l'autre).
 */
final class ImagePart implements ContentPartInterface
{
    public function __construct(
        public readonly ?string $url = null,
        public readonly ?string $base64 = null,
        public readonly string $mimeType = 'image/png',
    ) {
        if ($url === null && $base64 === null) {
            throw new InvalidArgumentException('ImagePart nécessite soit $url, soit $base64.');
        }
    }
}
