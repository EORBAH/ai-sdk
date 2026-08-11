# 01 — Architecture

## Le problème

Chaque fournisseur d'IA structure ses requêtes différemment. Deux exemples
qui donnent le ton :

- **Le "system prompt"** : chez OpenAI c'est un message comme un autre
  (`{"role": "system", ...}`) ; chez Anthropic c'est un paramètre séparé
  (`"system": "..."`), en dehors du tableau `messages`.
- **Le résultat d'un outil** : chez OpenAI c'est un message avec
  `"role": "tool"` ; chez Anthropic il n'existe pas de rôle "tool" — le
  résultat est un bloc `tool_result` à l'intérieur d'un message `"role": "user"`.

Si votre code applicatif parle directement le format d'un fournisseur, le
changer (ou simplement comparer deux modèles) veut dire réécrire toute la
couche d'intégration.

## La solution : un contrat commun + un adaptateur par fournisseur

```
                    ┌─────────────────────┐
 Votre code   ────▶ │   ChatRequest        │
 applicatif         │   (Message[], tools, │
                     │   responseSchema...) │
                     └──────────┬───────────┘
                                │
                     ┌──────────▼───────────┐
                     │  ProviderInterface    │  ← contrat unique
                     └──────────┬───────────┘
              ┌─────────────────┼─────────────────┬─────────────┐
              ▼                 ▼                 ▼             ▼
      OpenAIProvider    AnthropicProvider    XAIProvider   DeepSeekProvider...
      (traduit vers      (traduit vers        (étend OpenAIProvider,
       le format natif    le format natif      juste une autre base URI)
       OpenAI)             Anthropic)
              │                 │
              ▼                 ▼
         API OpenAI         API Anthropic
```

Votre code manipule toujours `ChatRequest`/`ChatResponse`/`Message` — jamais
directement le JSON natif d'un fournisseur. Chaque `*Provider` a la
responsabilité de :

1. **Construire** la requête native à partir de `ChatRequest` (`buildPayload()`).
2. **Parser** la réponse native en `ChatResponse` normalisée (`parseResponse()`).
3. Faire pareil en streaming, chunk par chunk (`stream()`).

## Les briques

| Classe | Rôle |
|---|---|
| `Message` | Un tour de conversation. `$content` est soit une simple chaîne, soit un tableau de blocs typés. |
| `TextPart` / `ImagePart` / `ToolUsePart` / `ToolResultPart` | Les types de blocs de contenu possibles (texte, image, appel d'outil, résultat d'outil). |
| `ChatRequest` | Ce que vous envoyez : modèle, messages, outils, schéma de sortie, température... |
| `ChatResponse` | Ce que vous recevez : texte, appels d'outils demandés, raison de fin, usage (tokens). |
| `StreamChunk` | Un fragment de réponse en streaming. |
| `Tool` / `ToolRegistry` | Définition d'un outil (nom, description, schéma JSON des paramètres, fonction PHP à exécuter) et sa collection. |
| `ToolRunner` | Exécute les appels d'outils demandés par le modèle et produit les messages de résultat. |
| `Agent` | Encapsule la boucle complète : envoyer → exécuter les outils demandés → renvoyer → recommencer, jusqu'à une réponse finale. |
| `Schema` | Petits raccourcis pour écrire un JSON Schema (paramètres d'outil ou structured output). |

## Pourquoi `Message::$content` accepte `string` OU un tableau de blocs

Le cas courant (texte simple) reste simple à écrire :

```php
Message::user('Bonjour'); // content = "Bonjour", une chaîne
```

Le cas riche (multimodal, tool calling) utilise des blocs typés, pour que
chaque provider sache précisément quoi faire de chaque morceau :

```php
Message::userWithImages('Que vois-tu ?', [new ImagePart(url: '...')]);
Message::assistantToolCalls([$toolCall]); // content = [ToolUsePart]
Message::toolResult($toolCallId, 'résultat'); // content = [ToolResultPart]
```

## Étendre le SDK à un nouveau fournisseur

Deux cas :

1. **Le fournisseur est "compatible OpenAI"** (xAI, DeepSeek, Mistral et
   beaucoup d'autres exposent une API Chat Completions quasi identique à
   OpenAI) : il suffit d'étendre `OpenAIProvider` et de changer l'URL de
   base, voir [`XAIProvider`](../src/Providers/XAI/XAIProvider.php).

2. **Le fournisseur a sa propre forme d'API** (comme Anthropic) : implémentez
   `ProviderInterface` directement — `chat()`, `stream()` — en vous inspirant
   de la structure d'[`AnthropicProvider`](../src/Providers/Anthropic/AnthropicProvider.php)
   (`buildPayload()`, `mapMessages()`, `parseResponse()`, `mapStopReason()`).
