<?php

/**
 * Search Service Provider
 *
 * Bootstraps semantic search: creates the embedding provider,
 * vector store, hooks into content lifecycle, and registers routes.
 */

declare(strict_types=1);

namespace Framework\Search;

use Framework\Core\Container;
use Framework\Plugin\HookSystem;

class SearchServiceProvider
{
    /**
     * Register the search service in the container and hook into content lifecycle.
     */
    public static function register(Container $container, HookSystem $hooks, array $config): void
    {
        if (!($config['enabled'] ?? true)) {
            return;
        }

        // Build embedding provider from config
        $embedder = self::createEmbedder($config);

        // Build search service
        $storagePath = rtrim($config['storage_path'] ?? 'storage/vectors', '/');
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $service = new SearchService(
            storagePath:      $storagePath,
            embedder:         $embedder,
            searchDimensions: (int) ($config['dimensions'] ?? 384),
            quantized:        (bool) ($config['quantized'] ?? true),
        );

        // Register in container
        $container->singleton(SearchService::class, fn() => $service);

        // Hook into content lifecycle for auto-indexing
        if ($config['auto_index'] ?? true) {
            $autoIndexTypes = $config['auto_index_types'] ?? ['post', 'page'];

            $indexFn = function ($content) use ($service, $autoIndexTypes) {
                if (!in_array($content->getType(), $autoIndexTypes, true)) {
                    return;
                }
                if ('published' !== $content->getStatus()) {
                    $service->remove($content->getType(), (string) $content->getId());
                    return;
                }
                try {
                    $service->indexContent($content);
                } catch (\Throwable $e) {
                    error_log("[Search] Failed to index content {$content->getId()}: {$e->getMessage()}");
                }
            };

            $hooks->addAction('content.created', $indexFn);
            $hooks->addAction('content.updated', $indexFn);

            $hooks->addAction('content.deleted', function ($content) use ($service) {
                $service->remove($content->getType(), (string) $content->getId());
            });
        }
    }

    /**
     * Register search routes.
     */
    public static function registerRoutes(\Framework\Core\Router $router, Container $container): void
    {
        $router->get('/api/search', function ($request) use ($container) {
            $controller = new SearchController(
                $container->get(SearchService::class),
                $container->get(\Framework\Content\ContentRepository::class),
            );
            return $controller->search($request);
        });

        $router->get('/api/search/stats', function () use ($container) {
            return (new SearchController(
                $container->get(SearchService::class),
                $container->get(\Framework\Content\ContentRepository::class),
            ))->stats();
        });

        $router->post('/api/search/reindex', function () use ($container) {
            return (new SearchController(
                $container->get(SearchService::class),
                $container->get(\Framework\Content\ContentRepository::class),
            ))->reindex();
        });
    }

    /**
     * Create an embedding provider from config.
     */
    private static function createEmbedder(array $config): EmbeddingProviderInterface
    {
        $provider     = $config['provider'] ?? 'ollama';
        $providerConf = $config['providers'][$provider] ?? [];

        return match ($provider) {
            'ollama' => new OllamaEmbeddingProvider(
                model:      $providerConf['model'] ?? 'embeddinggemma',
                host:       $providerConf['host'] ?? 'http://localhost:11434',
                dimensions: (int) ($providerConf['dimensions'] ?? 768),
            ),
            'cloudflare' => new HttpEmbeddingProvider(
                url:        "https://api.cloudflare.com/client/v4/accounts/{$providerConf['account_id']}/ai/run/{$providerConf['model']}",
                apiKey:     $providerConf['api_key'],
                dimensions: (int) ($providerConf['dimensions'] ?? 768),
                inputField: 'text',
                outputPath: 'result.data.0',
            ),
            'openai' => new HttpEmbeddingProvider(
                url:        'https://api.openai.com/v1/embeddings',
                apiKey:     $providerConf['api_key'],
                model:      $providerConf['model'] ?? 'text-embedding-3-small',
                dimensions: (int) ($providerConf['dimensions'] ?? 1536),
                inputField: 'input',
                outputPath: 'data.0.embedding',
            ),
            'custom' => new HttpEmbeddingProvider(
                url:        $providerConf['url'],
                apiKey:     $providerConf['api_key'] ?? '',
                model:      $providerConf['model'] ?? '',
                dimensions: (int) ($providerConf['dimensions'] ?? 768),
                inputField: $providerConf['input_field'] ?? 'input',
                outputPath: $providerConf['output_path'] ?? 'data.0.embedding',
            ),
            default => throw new \RuntimeException("Unknown search provider: {$provider}"),
        };
    }
}
