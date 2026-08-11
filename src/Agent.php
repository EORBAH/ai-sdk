<?php

declare(strict_types=1);

namespace EorBah545\AI;

use EorBah545\AI\Contracts\ProviderInterface;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\ChatResponse;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\Enum\Role;
use EorBah545\AI\Exceptions\AiException;

/**
 * Agent : encapsule la boucle "envoyer → si tool_calls, exécuter les outils
 * → renvoyer les résultats → recommencer" jusqu'à obtenir une réponse finale,
 * avec un tour de "réflexion" optionnel avant de renvoyer la réponse.
 *
 *   $agent = new Agent(
 *       provider: new OpenAIProvider($apiKey),
 *       model: 'gpt-4.1',
 *       tools: $tools,
 *       systemPrompt: 'Tu es un assistant utile.',
 *       reflect: true,
 *   );
 *   $response = $agent->run('Quelle est la météo à Conakry ?');
 *   echo $response->text;
 */
final class Agent
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly string $model,
        private readonly ?ToolRegistry $tools = null,
        private readonly ?string $systemPrompt = null,
        private readonly int $maxIterations = 8,
        private readonly bool $reflect = false,
        private readonly float $temperature = 1.0,
        private readonly ?array $responseSchema = null,
    ) {
    }

    public function run(string $userMessage): ChatResponse
    {
        $messages = [];
        if ($this->systemPrompt !== null) {
            $messages[] = Message::system($this->systemPrompt);
        }
        $messages[] = Message::user($userMessage);

        $runner = $this->tools !== null ? new ToolRunner($this->tools) : null;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $response = $this->provider->chat($this->buildRequest($messages));

            if (!$response->hasToolCalls()) {
                return $this->maybeReflect($messages, $response);
            }

            if ($runner === null) {
                throw new AiException('Le modèle a demandé un outil mais aucun ToolRegistry n\'a été fourni à l\'Agent.');
            }

            $messages[] = Message::assistantToolCalls($response->toolCalls);
            foreach ($runner->run($response) as $toolMessage) {
                $messages[] = $toolMessage;
            }
        }

        throw new AiException("Nombre maximal d'itérations ({$this->maxIterations}) atteint sans réponse finale.");
    }

    /**
     * Un unique tour de relecture critique avant de renvoyer la réponse
     * finale — le modèle peut se corriger lui-même.
     *
     * @param Message[] $messages
     */
    private function maybeReflect(array $messages, ChatResponse $response): ChatResponse
    {
        if (!$this->reflect) {
            return $response;
        }

        $messages[] = Message::assistant($response->text ?? '');
        $messages[] = Message::system(
            'Relis ta réponse ci-dessus de façon critique : est-elle complète, correcte, et répond-elle ' .
            'vraiment à la demande ? Si oui, renvoie-la telle quelle. Sinon, corrige-la et renvoie la version corrigée.'
        );

        return $this->provider->chat($this->buildRequest($messages));
    }

    /**
     * @param Message[] $messages
     */
    private function buildRequest(array $messages): ChatRequest
    {
        return new ChatRequest(
            model: $this->model,
            messages: $messages,
            tools: $this->tools,
            responseSchema: $this->responseSchema,
            temperature: $this->temperature,
        );
    }
}
