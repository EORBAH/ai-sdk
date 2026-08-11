<?php

declare(strict_types=1);

namespace EorBah545\AI\Providers\Manus;

use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\Exceptions\AiException;

/**
 * ⚠️ STUB — Manus (manus.im) est un produit d'agent IA, mais au moment de
 * la rédaction de ce package, aucune API publique de type "chat completions"
 * documentée n'a été trouvée pour Manus (contrairement à OpenAI/Anthropic/
 * xAI/DeepSeek/Mistral). Ce provider n'implémente donc rien de fonctionnel :
 * il respecte le contrat ProviderInterface pour que l'architecture reste
 * homogène, mais lève une exception à l'usage.
 *
 * Si Manus publie une API compatible (notamment si elle suit le format
 * OpenAI Chat Completions), il suffira de faire extends OpenAIProvider
 * avec la bonne base URI, comme XAIProvider/DeepSeekProvider/MistralProvider.
 * Vérifiez la documentation officielle Manus avant d'utiliser ce provider.
 */
final class ManusProvider implements ProviderInterface
{
    public function __construct(private readonly string $apiKey)
    {
    }

    public function getName(): string
    {
        return 'manus';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        throw new AiException(
            "ManusProvider n'est pas implémenté : aucune API publique confirmée au moment de l'écriture. " .
            'Adaptez cette classe (ou étendez OpenAIProvider si Manus expose une API compatible) une fois la documentation officielle disponible.'
        );
    }

    public function stream(ChatRequest $request): \Generator
    {
        throw new AiException("ManusProvider n'est pas implémenté (voir docblock de la classe).");
        yield; // rend la méthode un vrai générateur pour respecter le contrat
    }
}
