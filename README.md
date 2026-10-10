# ai-sdk

SDK PHP unifié pour les fournisseurs d'IA — **OpenAI, Anthropic, xAI (Grok),
DeepSeek, Mistral**, avec un cœur commun pour le tool calling, le streaming,
les agents, le structured output, les outils intégrés (recherche web, bash,
éditeur de texte, exécution de code), le batch, le cache de prompt et les
fichiers.

## Pourquoi

Chaque fournisseur a sa propre forme d'API (OpenAI et Anthropic ne
structurent même pas un simple message de la même façon). Ce package expose
un **contrat unique** (`ProviderInterface`, `Message`, `ChatRequest`,
`ChatResponse`) et un adaptateur par fournisseur qui traduit vers/depuis la
forme native de son API — votre code applicatif (agents, boucle de tool
calling, structured output) ne change pas quand vous changez de fournisseur.

## Installation

```bash
composer require eor_bah545/ai-sdk
```

## Démarrage rapide

```php
use EorBah545\AI\Providers\OpenAI\OpenAIProvider;
use EorBah545\AI\Providers\Anthropic\AnthropicProvider;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;

$provider = new OpenAIProvider(getenv('OPENAI_API_KEY'));
// ou : new AnthropicProvider(getenv('ANTHROPIC_API_KEY'));
// même code applicatif ensuite, quel que soit le fournisseur choisi

$response = $provider->chat(new ChatRequest(
    model: 'gpt-4.1',
    messages: [Message::user('Explique le tool calling en une phrase.')],
));

echo $response->text;
```

## Ce que couvre le SDK

| Fonctionnalité | Où |
|---|---|
| Chat simple, multi-tour | `ProviderInterface::chat()` |
| Streaming | `ProviderInterface::stream()` (Generator de `StreamChunk`) |
| Tool calling (function calling) | `Tool`, `ToolRegistry`, `ToolRunner` |
| Agents (boucle tool calling + réflexion) | `Agent` |
| Structured output (JSON Schema) | `Schema`, `ChatRequest::$responseSchema` |
| Raisonnement / reasoning effort | `ChatRequest::$reasoningEffort` |
| Outils intégrés : recherche web (Google) | `BuiltinTools\WebSearchTool` |
| Outils intégrés : bash | `BuiltinTools\BashTool` |
| Outils intégrés : éditeur de texte | `BuiltinTools\TextEditorTool` |
| Outils intégrés : exécution de code | `BuiltinTools\CodeExecutionTool` |
| Batch (traitement différé, moins cher) | `Providers\OpenAI\OpenAIBatchClient` |
| Fichiers | `Providers\OpenAI\OpenAIFileClient` |
| Cache de prompt | `Message::system(..., cache: true)` / `TextPart::$cache` |
| Images (vision) | `Message::userWithImages()`, `ImagePart` |

## Fournisseurs

| Provider | Statut | Base |
|---|---|---|
| `OpenAIProvider` | Implémentation de référence complète | API native |
| `AnthropicProvider` | Implémentation de référence complète | API native |
| `XAIProvider` (Grok) | Fonctionnel | étend `OpenAIProvider` (API compatible) |
| `DeepSeekProvider` | Fonctionnel | étend `OpenAIProvider` (API compatible) |
| `MistralProvider` | Fonctionnel | étend `OpenAIProvider` (API compatible) |
| `ManusProvider` | ⚠️ Stub non implémenté | aucune API publique confirmée au moment de l'écriture |

Voir [`docs/02-providers.md`](docs/02-providers.md) pour le détail.

## ⚠️ Important — tests et réseau

> peut contenir des bugs non corriger signaler les !

## Documentation & exemples

- [`docs/`](docs/) — architecture, tool calling, streaming, structured
  output, outils intégrés, batch/fichiers, cache de prompt.
- [`examples/`](examples/) — exemples de code par fonctionnalité (nécessitent
  de vraies clés API pour être exécutés).

## Tests

```bash
composer install
composer test
```
