<?php

declare(strict_types=1);

namespace EorBah545\AI\DTO;

/**
 * Marqueur commun à tous les types de contenu qu'un message peut porter
 * (texte, image, appel d'outil, résultat d'outil). Permet à Message::$content
 * d'être un tableau hétérogène typé plutôt qu'un simple array.
 */
interface ContentPartInterface
{
}
