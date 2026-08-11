<?php

declare(strict_types=1);

namespace EorBah545\AI\BuiltinTools;

use EorBah545\AI\Schema;
use EorBah545\AI\Tool;
use GuzzleHttp\Client;

/**
 * Outil de recherche web via l'API officielle Google Custom Search JSON.
 *
 * Docs officielles : https://developers.google.com/custom-search/v1/overview
 * Nécessite une clé API Google Cloud + un moteur de recherche programmable
 * (Programmable Search Engine, "cx") configuré pour chercher sur tout le web.
 *
 *   $tools->register(WebSearchTool::make($googleApiKey, $searchEngineId));
 */
final class WebSearchTool
{
    public static function make(string $apiKey, string $searchEngineId, int $maxResults = 5, ?Client $httpClient = null): Tool
    {
        $http = $httpClient ?? new Client(['base_uri' => 'https://www.googleapis.com/', 'timeout' => 15]);

        return new Tool(
            name: 'web_search',
            description: 'Recherche des informations récentes sur le web via Google et renvoie les résultats les plus pertinents (titre, extrait, lien).',
            parameters: Schema::object(
                ['query' => Schema::string('Termes de recherche')],
                required: ['query'],
            ),
            handler: function (array $args) use ($http, $apiKey, $searchEngineId, $maxResults): string {
                $response = $http->get('customsearch/v1', [
                    'query' => [
                        'key' => $apiKey,
                        'cx' => $searchEngineId,
                        'q' => $args['query'],
                        'num' => $maxResults,
                    ],
                ]);

                $data = json_decode((string) $response->getBody(), true) ?? [];
                $items = $data['items'] ?? [];

                if ($items === []) {
                    return "Aucun résultat pour « {$args['query']} ».";
                }

                $lines = [];
                foreach ($items as $item) {
                    $lines[] = "- {$item['title']}\n  {$item['link']}\n  " . ($item['snippet'] ?? '');
                }

                return implode("\n\n", $lines);
            },
        );
    }
}
