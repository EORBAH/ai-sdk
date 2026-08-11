# 07 — Batch & fichiers

## Fichiers — `OpenAIFileClient`

```php
use EorBah545\AI\Providers\OpenAI\OpenAIFileClient;

$files = new OpenAIFileClient(getenv('OPENAI_API_KEY'));

$fileId = $files->upload('/chemin/vers/document.pdf', purpose: 'batch');
$contenu = $files->content($fileId);
$files->delete($fileId);
```

## Batch — traitement différé, moins cher

Pour de gros volumes de requêtes non urgentes (généralement traitées sous
24h, à tarif réduit par rapport aux appels synchrones).

```php
use EorBah545\AI\Providers\OpenAI\OpenAIBatchClient;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;

$batch = new OpenAIBatchClient(getenv('OPENAI_API_KEY'));

$batchId = $batch->submit([
    ['custom_id' => 'commande-1', 'request' => new ChatRequest('gpt-4.1', [Message::user('Résume ce texte : ...')])],
    ['custom_id' => 'commande-2', 'request' => new ChatRequest('gpt-4.1', [Message::user('Résume ce texte : ...')])],
    // ... des milliers de requêtes si besoin
]);

// Plus tard (polling, cron...) :
$statut = $batch->status($batchId);
// ['status' => 'in_progress'|'completed'|..., 'completed' => 42, 'total' => 100]

if ($statut['status'] === 'completed') {
    $resultats = $batch->results($batchId); // ['commande-1' => ChatResponse, 'commande-2' => ChatResponse, ...]
    foreach ($resultats as $customId => $response) {
        echo "{$customId} : {$response->text}\n";
    }
}
```

`custom_id` est à vous de le choisir — c'est ce qui vous permet de relier
chaque résultat à la requête d'origine une fois le batch terminé.

## Étendre à un autre fournisseur

`BatchClientInterface` et `FileClientInterface` (dans `AI\Contracts\`) sont
les contrats à implémenter pour ajouter le support batch/fichiers d'un autre
fournisseur (Anthropic propose aussi une Message Batches API suivant un
principe similaire — non implémentée dans cette version du package, mais le
même patron de conception s'applique).

**Exemple de code :** [`examples/07-batch.php`](../examples/07-batch.php)
