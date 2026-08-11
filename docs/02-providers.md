# 02 — Fournisseurs

## Se connecter

```php
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Providers\Anthropic\AnthropicProvider;
use EorBah545\AI\Providers\XAI\XAIProvider;
use EorBah545\AI\Providers\DeepSeek\DeepSeekProvider;
use EorBah545\AI\Providers\Mistral\MistralProvider;

$openai   = new OpenAIProvider(getenv('OPENAI_API_KEY'));
$claude   = new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));
$grok     = new XAIProvider(getenv('XAI_API_KEY'));
$deepseek = new DeepSeekProvider(getenv('DEEPSEEK_API_KEY'));
$mistral  = new MistralProvider(getenv('MISTRAL_API_KEY'));
```

Tous implémentent `ProviderInterface` — le reste de votre code (agents,
tool calling, streaming, structured output) est **identique** quel que soit
le provider choisi.

```php
function repondre(\AI\Contracts\ProviderInterface $provider, string $modele, string $question): string
{
    $response = $provider->chat(new \AI\DTO\ChatRequest($modele, [\AI\DTO\Message::user($question)]));
    return $response->text;
}

repondre($openai, 'gpt-4.1', 'Bonjour');
repondre($claude, 'claude-sonnet-4-6', 'Bonjour'); // même fonction, aucune modification
```

## Détail par fournisseur

### OpenAI — implémentation de référence

API Chat Completions officielle. Cache de prompt **automatique** dès que le
préfixe dépasse 1024 tokens (visible dans
`$response->usage->cachedInputTokens`, rien à faire côté appelant).

Docs : https://platform.openai.com/docs/api-reference/chat

### Anthropic — implémentation de référence

API Messages officielle. Différences gérées en interne (voir
[01 — Architecture](01-architecture.md)) : system prompt séparé, pas de rôle
"tool" (le résultat passe côté `user`), `max_tokens` obligatoire (4096 par
défaut si non précisé), cache de prompt **explicite** (`Message::system(..., cache: true)`).

Docs : https://docs.claude.com/en/api/messages

### xAI (Grok)

`XAIProvider extends OpenAIProvider` — l'API xAI suit le format OpenAI Chat
Completions. ⚠️ Le support exact de certains paramètres avancés
(`reasoning_effort`, `response_format` strict) peut varier selon le modèle
Grok utilisé — vérifiez la doc xAI pour le modèle ciblé.

Docs : https://docs.x.ai/docs/api-reference

### DeepSeek

`DeepSeekProvider extends OpenAIProvider` — API compatible OpenAI. Le modèle
`deepseek-reasoner` expose sa trace de raisonnement dans un champ
`reasoning_content` non repris automatiquement par `ChatResponse` — lisez
`$response->raw` si vous en avez besoin.

Docs : https://api-docs.deepseek.com

### Mistral

`MistralProvider extends OpenAIProvider` — API très proche du format OpenAI.
⚠️ Le support de `response_format` (structured output) et de
`reasoning_effort` dépend du modèle Mistral utilisé.

Docs : https://docs.mistral.ai/api/

### Manus — ⚠️ non implémenté

`ManusProvider` existe pour respecter l'architecture mais **lève une
exception à l'usage** : au moment de la rédaction de ce package, aucune API
publique de type "chat completions" documentée n'a été trouvée pour Manus.
Si Manus publie une API compatible OpenAI, il suffira de la faire hériter
d'`OpenAIProvider` comme les trois précédents. Vérifiez la documentation
officielle Manus avant d'utiliser ce provider en pratique.

## Ajouter un fournisseur non listé ici

Voir la section "Étendre le SDK à un nouveau fournisseur" dans
[01 — Architecture](01-architecture.md#étendre-le-sdk-à-un-nouveau-fournisseur).
