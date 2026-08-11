# 04 — Streaming

`ProviderInterface::stream()` renvoie un `Generator<StreamChunk>` — vous le
parcourez avec un `foreach`, le texte arrive au fur et à mesure.

```php
foreach ($provider->stream(new ChatRequest('gpt-4.1', $messages)) as $chunk) {
    if ($chunk->deltaText !== '') {
        echo $chunk->deltaText; // afficher au fur et à mesure
    }
    if ($chunk->done) {
        echo "\n[terminé — {$chunk->usage->outputTokens} tokens générés]";
    }
}
```

## Streaming avec tool calling

Les appels d'outils arrivent aussi sous forme de chunks (`$chunk->toolCall`),
une fois qu'ils sont complets (les arguments arrivent fragmentés chez la
plupart des fournisseurs — le SDK les réassemble pour vous, vous ne recevez
le `ToolCall` qu'une fois complet et valide) :

```php
$toolCalls = [];
$texte = '';

foreach ($provider->stream(new ChatRequest('gpt-4.1', $messages, $tools)) as $chunk) {
    if ($chunk->deltaText !== '') {
        $texte .= $chunk->deltaText;
        echo $chunk->deltaText;
    }
    if ($chunk->toolCall !== null) {
        $toolCalls[] = $chunk->toolCall;
    }
}

if ($toolCalls !== []) {
    // exécutez-les comme en mode non-streaming (voir ToolRunner)
}
```

## Ce que renvoie le chunk final

Le dernier chunk d'un flux a `done: true` et porte :
- `finishReason` — pourquoi la génération s'est arrêtée (`Stop`, `ToolCalls`, `Length`...)
- `usage` — tokens d'entrée/sortie (et tokens mis en cache, si applicable)

## `Agent` ne fait pas de streaming (pour l'instant)

`Agent::run()` utilise `chat()`, pas `stream()` — il attend la réponse
complète à chaque tour pour pouvoir décider s'il doit exécuter des outils.
Si vous avez besoin d'afficher la réponse finale en streaming après une
boucle de tool calling, faites le dernier tour manuellement avec `stream()`
une fois que vous savez qu'il n'y a plus de tool calls à venir.

**Exemple de code :** [`examples/03-streaming.php`](../examples/03-streaming.php)
