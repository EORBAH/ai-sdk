# 05 — Structured output & cache de prompt

## Structured output — obtenir du JSON garanti

```php
use EorBah545\AI\Schema;
use EorBah545\AI\DTO\ChatRequest;
use EorBah545\AI\DTO\Message;

$schema = Schema::object([
    'nom' => Schema::string('Nom complet de la personne'),
    'age' => Schema::integer('Âge en années'),
    'pays' => Schema::string(enum: ['GN', 'FR', 'US']),
], required: ['nom', 'age', 'pays']);

$response = $provider->chat(new ChatRequest(
    model: 'gpt-4.1',
    messages: [Message::user('Aissatou a 25 ans et vit en Guinée.')],
    responseSchema: $schema,
));

$data = $response->json(); // ['nom' => 'Aissatou', 'age' => 25, 'pays' => 'GN']
```

`$response->json()` décode `$response->text` — le SDK garantit que ce texte
est un JSON valide respectant `$schema`, quel que soit le fournisseur.

### Comment c'est implémenté selon le fournisseur

- **OpenAI** : utilise le mode natif `response_format: {type: json_schema}`
  (mode "strict") — le fournisseur garantit lui-même la conformité.
- **Anthropic** : n'a pas d'équivalent natif au moment de l'écriture. Le SDK
  simule ça en déclarant un outil synthétique dont le schéma **est**
  `$schema`, en forçant le modèle à l'appeler (`tool_choice`), puis en
  réinjectant son `input` comme texte JSON dans `$response->text`. Totalement
  transparent pour vous — `$response->json()` fonctionne pareil.

## Cache de prompt — réduire coût et latence sur un contexte répété

Utile pour un system prompt long et stable (instructions, exemples,
documentation) réutilisé sur beaucoup d'appels.

```php
use EorBah545\AI\DTO\Message;

$messages = [
    Message::system($longSystemPromptStable, cache: true), // marqué pour le cache
    Message::user($questionDuUtilisateur), // change à chaque appel, jamais caché
];
```

### Comment c'est géré selon le fournisseur

- **OpenAI** : cache **automatique** dès que le préfixe de prompt dépasse
  1024 tokens — le flag `cache: true` est accepté sans erreur mais n'a
  aucun effet, OpenAI gère ça seul. Les tokens effectivement mis en cache
  apparaissent dans `$response->usage->cachedInputTokens`.
- **Anthropic** : cache **explicite** — `cache: true` ajoute un
  `cache_control: {type: ephemeral}` sur le bloc concerné, ce qui déclenche
  réellement la création/lecture du cache côté Anthropic. Sans ce flag,
  aucun cache n'est utilisé même sur un prompt répété.

## Images (vision)

```php
use EorBah545\AI\DTO\Message;
use EorBah545\AI\DTO\ImagePart;

$messages[] = Message::userWithImages(
    'Décris cette image.',
    [new ImagePart(url: 'https://exemple.com/photo.jpg')],
);

// ou en base64 :
$messages[] = Message::userWithImages('Décris cette image.', [
    new ImagePart(base64: base64_encode(file_get_contents('photo.jpg')), mimeType: 'image/jpeg'),
]);
```

**Exemple de code :** [`examples/05-structured-output.php`](../examples/05-structured-output.php)
