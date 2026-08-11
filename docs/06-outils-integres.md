# 06 — Outils intégrés

Quatre outils prêts à l'emploi dans `AI\BuiltinTools\`, à enregistrer comme
n'importe quel `Tool` dans un `ToolRegistry`.

## ⚠️ Lire avant d'utiliser BashTool ou CodeExecutionTool

Ces deux outils exécutent **exactement** ce que le modèle décide de leur
donner (commande shell ou code source). Ce n'est pas un défaut du package —
c'est la nature même de ce type d'outil (identique à "code execution" chez
OpenAI/Anthropic) — mais ça veut dire :

- **jamais** sur une machine de production, avec des identifiants réels, ou
  un accès à des données sensibles ;
- toujours dans un environnement **isolé et jetable** (conteneur sans accès
  réseau ni secrets, VM sacrifiable) ;
- gardez en tête qu'un modèle mal aligné, mal prompté, ou manipulé par du
  contenu malveillant dans ses entrées (prompt injection via un document,
  une page web...) peut décider d'exécuter n'importe quoi dans les limites
  de ce que le sandbox autorise.

## WebSearchTool — recherche web (Google)

```php
use EorBah545\AI\BuiltinTools\WebSearchTool;

$tools->register(WebSearchTool::make(
    apiKey: getenv('GOOGLE_API_KEY'),
    searchEngineId: getenv('GOOGLE_CX'), // Programmable Search Engine
    maxResults: 5,
));
```

Nécessite une clé API Google Cloud + un moteur de recherche programmable
configuré pour chercher sur tout le web (pas juste un site).
Docs : https://developers.google.com/custom-search/v1/overview

## BashTool — exécution de commandes shell

```php
use EorBah545\AI\BuiltinTools\BashTool;

$tools->register(BashTool::make(workingDirectory: '/sandbox', timeoutSeconds: 20));
```

## TextEditorTool — lire/créer/modifier des fichiers, sandboxé

```php
use EorBah545\AI\BuiltinTools\TextEditorTool;

$tools->register(TextEditorTool::make('/sandbox/projet'));
```

Trois commandes exposées au modèle : `view` (lire), `create` (créer/écraser),
`str_replace` (remplacer un extrait unique). **Toute tentative de sortir du
dossier fourni est refusée** (protection contre le path traversal, testée
avec `../../../etc/passwd`).

## CodeExecutionTool — exécuter un extrait de code

```php
use EorBah545\AI\BuiltinTools\CodeExecutionTool;

$tools->register(CodeExecutionTool::make(['php', 'python'], timeoutSeconds: 15));
```

Écrit le code dans un fichier temporaire et l'exécute via le bon interpréteur
(`php`, `python3`). Utile quand le modèle préfère exprimer une tâche de
calcul/données en code plutôt qu'en commande shell.

## Les combiner dans un Agent

```php
use EorBah545\AI\Agent;
use EorBah545\AI\ToolRegistry;
use EorBah545\AI\BuiltinTools\{WebSearchTool, BashTool, TextEditorTool, CodeExecutionTool};

$tools = new ToolRegistry();
$tools->register(WebSearchTool::make($googleKey, $googleCx));
$tools->register(BashTool::make('/sandbox'));
$tools->register(TextEditorTool::make('/sandbox'));
$tools->register(CodeExecutionTool::make(['python']));

$agent = new Agent($provider, 'gpt-4.1', $tools, systemPrompt: 'Tu es un agent de développement.');
$response = $agent->run('Crée un script Python qui calcule les 10 premiers nombres premiers, exécute-le, et montre le résultat.');
```

**Exemple de code :** [`examples/06-builtin-tools.php`](../examples/06-builtin-tools.php)
