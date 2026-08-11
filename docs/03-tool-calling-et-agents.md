# 03 — Tool calling & agents

## Définir un outil

```php
use EorBah545\AI\Tool;
use EorBah545\AI\Schema;

$getWeather = new Tool(
    name: 'get_weather',
    description: 'Renvoie la météo actuelle pour une ville.',
    parameters: Schema::object(
        ['city' => Schema::string('Nom de la ville')],
        required: ['city'],
    ),
    handler: function (array $args): string {
        // votre vraie logique ici — appel API météo, base de données...
        return "Il fait 28°C et ensoleillé à {$args['city']}.";
    },
);
```

`handler` reçoit les arguments **déjà décodés** (JSON → tableau PHP) et doit
renvoyer une chaîne — c'est ce que le modèle relira au tour suivant.

## Regrouper les outils

```php
use EorBah545\AI\ToolRegistry;

$tools = new ToolRegistry();
$tools->register($getWeather);
$tools->register($autreOutil);
```

## Boucle manuelle (contrôle total)

```php
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;
use EorBah545\AI\ToolRunner;

$messages = [Message::user('Quelle météo à Conakry ?')];
$runner = new ToolRunner($tools);

while (true) {
    $response = $provider->chat(new ChatRequest('gpt-4.1', $messages, $tools));

    if (!$response->hasToolCalls()) {
        echo $response->text;
        break;
    }

    $messages[] = Message::assistantToolCalls($response->toolCalls);
    foreach ($runner->run($response) as $toolResultMessage) {
        $messages[] = $toolResultMessage;
    }
}
```

## `Agent` — la même boucle, prête à l'emploi

```php
use EorBah545\AI\Agent;

$agent = new Agent(
    provider: $provider,
    model: 'gpt-4.1',
    tools: $tools,
    systemPrompt: 'Tu es un assistant météo utile et concis.',
    maxIterations: 8, // garde-fou : lève une AiException au-delà
);

$response = $agent->run('Quelle météo à Conakry ?');
echo $response->text;
```

Si le modèle demande un outil qui n'est pas dans le `ToolRegistry`,
`ToolRunner` renvoie un message d'erreur **au modèle** (pas d'exception PHP)
— il peut ainsi se corriger. Si un handler PHP lève une exception, elle est
aussi transformée en résultat d'erreur pour le modèle plutôt que de faire
planter votre application.

## Réflexion (auto-critique avant la réponse finale)

```php
$agent = new Agent($provider, 'gpt-4.1', $tools, reflect: true);
```

Une fois la réponse finale obtenue (plus de tool calls), l'agent fait **un**
tour supplémentaire en demandant au modèle de relire sa réponse de façon
critique et de la corriger si besoin, avant de la renvoyer. Simple et
efficace pour des tâches où la première réponse a tendance à être un peu
bâclée (résumés, raisonnement multi-étapes).

## Raisonnement (reasoning effort)

Pour les modèles qui exposent un mode de raisonnement explicite
(o-series OpenAI, DeepSeek-R1...) :

```php
new ChatRequest('o4-mini', $messages, reasoningEffort: 'high'); // 'low'|'medium'|'high'
```

Transmis tel quel au fournisseur ; ignoré silencieusement par les modèles
qui ne le supportent pas.

**Exemples de code :**
[`examples/02-tool-calling.php`](../examples/02-tool-calling.php),
[`examples/04-agent.php`](../examples/04-agent.php)
