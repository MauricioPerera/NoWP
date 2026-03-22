<?php

declare(strict_types=1);

namespace Framework\Agent;

use Framework\Agent\Provider\OllamaProvider;
use Framework\Agent\Provider\HttpProvider;
use Framework\Agent\Tools\Tool;
use Framework\Agent\Workflow\WorkflowEngine;
use Framework\Agent\Memory\MemoryService;
use Framework\Core\Container;
use Framework\Core\Router;
use Framework\Search\SearchService;
use Framework\Content\ContentRepository;
use Framework\Content\ContentService;

class AgentServiceProvider
{
    public static function register(Container $container, array $config): void
    {
        if (!($config['enabled'] ?? true)) {
            return;
        }

        // Build AI provider
        $provider = self::createProvider($config);

        // Build workflow engine
        $workflow = new WorkflowEngine();

        // Build memory service (if search is available and memory is enabled)
        $memory = null;
        if (($config['memory_enabled'] ?? true) && $container->has(SearchService::class)) {
            $memory = new MemoryService(
                $container->get(SearchService::class),
                $config['memory_path'] ?? 'storage/agent/memory',
            );
        }

        // Build agent
        $agent = new AgentService(
            provider:     $provider,
            workflow:     $workflow,
            memory:       $memory,
            systemPrompt: $config['system_prompt'] ?? '',
            agentId:      $config['id'] ?? 'default',
        );

        // Register built-in tools
        self::registerBuiltinTools($agent, $container, $config);

        $container->singleton(AgentService::class, fn() => $agent);
    }

    public static function registerRoutes(Router $router, Container $container): void
    {
        $make = fn() => new AgentController($container->get(AgentService::class));

        $router->post('/api/agent/chat', fn($req) => $make()->chat($req));
        $router->post('/api/agent/workflow', fn($req) => $make()->workflow($req));
        $router->post('/api/agent/memory', fn($req) => $make()->saveMemory($req));
        $router->get('/api/agent/memory', fn($req) => $make()->recallMemory($req));
        $router->post('/api/agent/reset', fn() => $make()->reset());
    }

    private static function createProvider(array $config): Provider\AIProviderInterface
    {
        $name = $config['provider'] ?? 'ollama';
        $conf = $config['providers'][$name] ?? [];

        return match ($name) {
            'ollama' => new OllamaProvider(
                model:       $conf['model'] ?? 'llama3.1',
                host:        $conf['host'] ?? 'http://localhost:11434',
                temperature: (float)($conf['temperature'] ?? 0.7),
            ),
            default => new HttpProvider(
                url:         $conf['url'] ?? '',
                apiKey:      $conf['api_key'] ?? '',
                model:       $conf['model'] ?? '',
                temperature: (float)($conf['temperature'] ?? 0.7),
                maxTokens:   (int)($conf['max_tokens'] ?? 4096),
            ),
        };
    }

    private static function registerBuiltinTools(AgentService $agent, Container $container, array $config): void
    {
        $enabled = $config['builtin_tools'] ?? [];

        if (in_array('search_content', $enabled) && $container->has(SearchService::class)) {
            $search = $container->get(SearchService::class);
            $agent->addTool(
                Tool::make('search_content', 'Search site content semantically by meaning, not just keywords.')
                    ->param('query', 'string', 'Natural language search query', true)
                    ->param('type', 'string', 'Content type filter (post, page). Optional.')
                    ->param('limit', 'integer', 'Max results (default 5)')
                    ->handler(fn($query, $type = '', $limit = 5) =>
                        $type
                            ? $search->hybridSearch($type, $query, (int)$limit, ['status' => 'published'])
                            : $search->searchAll($query, (int)$limit)
                    )
            );
        }

        if (in_array('get_content', $enabled) && $container->has(ContentRepository::class)) {
            $repo = $container->get(ContentRepository::class);
            $agent->addTool(
                Tool::make('get_content', 'Get a content item by its ID. Returns title, body, type, status.')
                    ->param('id', 'integer', 'Content ID', true)
                    ->handler(fn($id) => $repo->find((int)$id)?->toArray() ?? ['error' => 'Not found'])
            );
        }

        if (in_array('create_content', $enabled) && $container->has(ContentService::class)) {
            $service = $container->get(ContentService::class);
            $agent->addTool(
                Tool::make('create_content', 'Create a new content item (post or page).')
                    ->param('title', 'string', 'Content title', true)
                    ->param('body', 'string', 'Content body (HTML allowed)', true)
                    ->param('type', 'string', 'Content type: post or page (default: post)')
                    ->param('status', 'string', 'Status: draft or published (default: draft)')
                    ->handler(fn($title, $body, $type = 'post', $status = 'draft') =>
                        $service->create([
                            'title' => $title, 'content' => $body,
                            'type' => $type, 'status' => $status,
                            'author_id' => 1,
                        ])->toArray()
                    )
            );
        }
    }
}
