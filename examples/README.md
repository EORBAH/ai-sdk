# Exemples

⚠️ Contrairement aux exemples des autres packages, ceux-ci **nécessitent de
vraies clés API** (OpenAI, Anthropic...) pour s'exécuter — ils ne peuvent pas
tourner sans réseau.

```bash
export OPENAI_API_KEY=sk-...
export ANTHROPIC_API_KEY=sk-ant-...
export MISTRAL_API_KEY=sk-...
composer install
php examples/01-chat-simple.php
```

| Fichier | Couvre | Doc associée |
|---|---|---|
| [`01-chat-simple.php`](01-chat-simple.php) | Chat basique, même code pour OpenAI et Anthropic | [docs/02](../docs/02-providers.md) |
| [`02-tool-calling.php`](02-tool-calling.php) | Boucle de tool calling manuelle avec `ToolRunner` | [docs/03](../docs/03-tool-calling-et-agents.md) |
| [`03-streaming.php`](03-streaming.php) | Affichage au fur et à mesure | [docs/04](../docs/04-streaming.md) |
| [`04-agent.php`](04-agent.php) | `Agent` : boucle automatique + réflexion | [docs/03](../docs/03-tool-calling-et-agents.md) |
| [`05-structured-output.php`](05-structured-output.php) | JSON garanti via `Schema` | [docs/05](../docs/05-structured-output-et-cache.md) |
| [`06-builtin-tools.php`](06-builtin-tools.php) | web_search, bash, éditeur, exécution de code | [docs/06](../docs/06-outils-integres.md) |
| [`07-batch.php`](07-batch.php) | Traitement en lot différé | [docs/07](../docs/07-batch-et-fichiers.md) |
| [`08-multi-provider.php`](08-multi-provider.php) | Faire tourner la même tâche sur 3 fournisseurs | [docs/02](../docs/02-providers.md) |
