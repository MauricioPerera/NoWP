<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent;

use ChimeraNoWP\Agent\Bridge\CMSBridge;
use ChimeraNoWP\Agent\Bridge\MemoryBridge;
use ChimeraNoWP\Agent\Bridge\WorkflowBridge;
use ChimeraNoWP\Agent\Core\EventEmitter;
use ChimeraNoWP\Agent\Core\ToolDefinition;
use ChimeraNoWP\Agent\Core\ToolRegistry;
use ChimeraNoWP\Agent\LLM\OllamaProvider;
use ChimeraNoWP\Agent\LLM\OpenAIProvider;
use ChimeraNoWP\Agent\LLM\OpenRouterProvider;
use ChimeraNoWP\Agent\LLM\ProviderInterface;
use ChimeraNoWP\Agent\LLM\WorkersAIProvider;
use ChimeraNoWP\Agent\MCP\MCPController;
use ChimeraNoWP\Agent\MCP\MCPServer;
use ChimeraNoWP\Agent\Memory\MemoryService;
use ChimeraNoWP\Agent\Memory\SessionStore;
use ChimeraNoWP\Agent\Workflow\WorkflowEngine;
use ChimeraNoWP\Agent\Workflow\Scheduler;
use ChimeraNoWP\Agent\Data\EntitySchema;
use ChimeraNoWP\Agent\Data\EntityMaterializer;
use ChimeraNoWP\Agent\Testing\TestRunner;
use ChimeraNoWP\Agent\Integration\ServiceDefinition;
use ChimeraNoWP\Agent\Integration\IntegrationManager;
use ChimeraNoWP\Agent\Page\PageBuilder;
use ChimeraNoWP\Agent\Page\ComponentCatalog;
use ChimeraNoWP\Agent\Project;
use ChimeraNoWP\Agent\Scaffolding;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\Router;
use ChimeraNoWP\Search\SearchService;
use ChimeraNoWP\Content\ContentRepository;
use ChimeraNoWP\Content\ContentService;
use ChimeraNoWP\Database\Connection;

class AgentServiceProvider
{
    public static function register(Container $container, array $config): void
    {
        if (!($config['enabled'] ?? true)) {
            return;
        }

        // Build LLM provider (supports 4 providers)
        $provider = self::createProvider($config);

        // Build workflow engine
        $workflow = new WorkflowEngine();
        $container->singleton(WorkflowEngine::class, fn() => $workflow);

        // Build memory service (if search is available)
        $memory = null;
        if (($config['memory_enabled'] ?? true) && $container->has(SearchService::class)) {
            $memory = new MemoryService(
                $container->get(SearchService::class),
                $config['memory_path'] ?? 'storage/agent/memory',
            );
            $container->singleton(MemoryService::class, fn() => $memory);
        }

        // Build tool registry and event emitter
        $tools = new ToolRegistry();
        $events = new EventEmitter();

        // Build session store (SQLite for conversation history)
        $sessionStore = null;
        try {
            $sessionPath = ($config['data_dir'] ?? 'storage/agent') . '/sessions.db';
            $sessionStore = new SessionStore($sessionPath);
        } catch (\Throwable) {
            // SQLite not available, sessions disabled
        }

        // Build agent facade
        $agentId = $config['id'] ?? 'chimera';
        $facade = new AgentFacade(
            provider: $provider,
            memory: $memory,
            workflowEngine: $workflow,
            tools: $tools,
            events: $events,
            sessions: $sessionStore,
            systemPrompt: $config['system_prompt'] ?? '',
            agentId: $agentId,
            userId: 'default',
            maxIterations: (int) ($config['max_iterations'] ?? 25),
        );

        // Register CMS bridge tools
        if ($container->has(ContentService::class) && $container->has(ContentRepository::class) && $container->has(SearchService::class)) {
            $cmsTools = CMSBridge::tools(
                $container->get(ContentService::class),
                $container->get(ContentRepository::class),
                $container->get(SearchService::class),
            );
            foreach ($cmsTools as $tool) {
                $tools->register($tool);
            }
        }

        // Register memory bridge tools
        if ($memory) {
            foreach (MemoryBridge::tools($memory, $agentId) as $tool) {
                $tools->register($tool);
            }
        }

        // Register workflow bridge tools
        foreach (WorkflowBridge::tools($workflow) as $tool) {
            $tools->register($tool);
        }

        // Register optional Chimera bridges (Shell, A2E)
        self::loadOptionalBridges($tools);

        // Build A2D materializer
        if ($container->has(Connection::class)) {
            $materializer = new EntityMaterializer(
                $container->get(Connection::class),
                $container->has(SearchService::class) ? $container->get(SearchService::class) : null,
            );
            $container->singleton(EntityMaterializer::class, fn() => $materializer);
        }

        // Build A2I integration manager
        $integrationPath = $config['integration_path'] ?? 'storage/agent/integrations';
        $integrations = new IntegrationManager($integrationPath);
        $integrations->bootTools($facade);
        $container->singleton(IntegrationManager::class, fn() => $integrations);

        // Build Scheduler
        $schedulerPath = $config['scheduler_path'] ?? 'storage/agent/schedules';
        $scheduler = new Scheduler($workflow, $schedulerPath);
        $container->singleton(Scheduler::class, fn() => $scheduler);

        // Build A2P page builder
        $pagePath = $config['pages_path'] ?? 'storage/agent/pages';
        $pageBuilder = new PageBuilder($pagePath, $facade);
        $container->singleton(PageBuilder::class, fn() => $pageBuilder);

        // Build Project Manager
        $projectsPath = $config['projects_path'] ?? 'storage/projects';
        $projectManager = new Project\ProjectManager($projectsPath);
        $container->singleton(Project\ProjectManager::class, fn() => $projectManager);

        // Build Scaffolding Engine
        $scaffoldPath = $config['scaffolding_path'] ?? 'storage/agent/scaffolding';
        $scaffolding = new Scaffolding\ScaffoldingEngine($facade, $scaffoldPath, $provider);
        $container->singleton(Scaffolding\ScaffoldingEngine::class, fn() => $scaffolding);

        // Register extended tools (A2D, A2I, scheduler, A2P, etc.)
        self::registerExtendedTools($tools, $container, $config, $facade);

        // Register facade in container
        $container->singleton(AgentFacade::class, fn() => $facade);

        // Backwards compatibility alias
        $container->singleton('agent', fn() => $facade);
    }

    public static function registerRoutes(Router $router, Container $container): void
    {
        $make = fn() => new AgentController($container->get(AgentFacade::class));

        // Core agent endpoints
        $router->get('/api/agent/tools', fn() => $make()->listTools());
        $router->post('/api/agent/tools/{name}', fn($req, $name) => $make()->executeTool($req, $name));
        $router->post('/api/agent/chat', fn($req) => $make()->chat($req));
        $router->post('/api/agent/workflow', fn($req) => $make()->workflow($req));
        $router->post('/api/agent/memory', fn($req) => $make()->saveMemory($req));
        $router->get('/api/agent/memory', fn($req) => $make()->recallMemory($req));
        $router->post('/api/agent/reset', fn() => $make()->reset());

        // Memory management routes
        if ($container->has(MemoryService::class)) {
            $mem = fn() => $container->get(MemoryService::class);

            $router->get('/api/memory/stats', function ($req) use ($mem) {
                $agentId = $req->query('agent_id', 'default');
                $userId = $req->query('user_id', 'default');
                return $mem()->stats($agentId, $userId);
            });

            $router->post('/api/memory/recall', function ($req) use ($mem) {
                $body = $req->json();
                return $mem()->recall(
                    $body['agent_id'] ?? 'default',
                    $body['user_id'] ?? 'default',
                    $body['query'] ?? '',
                    (int) ($body['max_items'] ?? 20),
                    (int) ($body['max_chars'] ?? 8000),
                    $body['collections'] ?? ['memories', 'skills', 'knowledge'],
                    $body['weights'] ?? [],
                );
            });

            $router->post('/api/memory/memories', function ($req) use ($mem) {
                $b = $req->json();
                return $mem()->saveMemory($b['agent_id'] ?? 'default', $b['user_id'] ?? 'default', $b['content'] ?? '', $b['category'] ?? 'fact', $b['tags'] ?? []);
            });

            $router->post('/api/memory/skills', function ($req) use ($mem) {
                $b = $req->json();
                return $mem()->saveSkill($b['agent_id'] ?? 'default', $b['content'] ?? '', $b['category'] ?? 'procedure', $b['tags'] ?? []);
            });

            $router->post('/api/memory/knowledge', function ($req) use ($mem) {
                $b = $req->json();
                return $mem()->saveKnowledge($b['agent_id'] ?? 'default', $b['content'] ?? '', $b['tags'] ?? [], $b['source'] ?? null);
            });

            $router->post('/api/memory/sessions', function ($req) use ($mem) {
                $b = $req->json();
                return $mem()->saveSession($b['agent_id'] ?? 'default', $b['user_id'] ?? 'default', $b['content'] ?? '', $b['messages'] ?? []);
            });

            $router->post('/api/memory/profiles', function ($req) use ($mem) {
                $b = $req->json();
                return $mem()->saveProfile($b['agent_id'] ?? 'default', $b['user_id'] ?? 'default', $b['content'] ?? '', $b['metadata'] ?? []);
            });

            $router->get('/api/memory/profiles/{agentId}/{userId}', function ($req, $agentId, $userId) use ($mem) {
                return $mem()->getProfile($agentId, $userId) ?? ['error' => 'No profile'];
            });
        }

        // A2D entity endpoints
        if ($container->has(EntityMaterializer::class)) {
            $mat = fn() => $container->get(EntityMaterializer::class);

            $router->get('/api/entities', fn() => $mat()->listSchemas());
            $router->post('/api/entities', function ($req) use ($mat) {
                $schema = new EntitySchema($req->json());
                return $mat()->materialize($schema);
            });
            $router->get('/api/entities/{entity}', function ($req, $entity) use ($mat) {
                $filters = $req->json() ?: [];
                $limit = (int) $req->query('limit', 50);
                return $mat()->findAll($entity, $filters, $limit);
            });
            $router->post('/api/entities/{entity}', function ($req, $entity) use ($mat) {
                return $mat()->insert($entity, $req->json());
            });
            $router->get('/api/entities/{entity}/{id}', function ($req, $entity, $id) use ($mat) {
                return $mat()->find($entity, (int) $id) ?? ['error' => 'Not found'];
            });
            $router->put('/api/entities/{entity}/{id}', function ($req, $entity, $id) use ($mat) {
                return $mat()->update($entity, (int) $id, $req->json());
            });
            $router->delete('/api/entities/{entity}/{id}', function ($req, $entity, $id) use ($mat) {
                return $mat()->delete($entity, (int) $id);
            });
        }

        // Scheduler endpoints
        if ($container->has(Scheduler::class)) {
            $sched = fn() => $container->get(Scheduler::class);
            $router->get('/api/schedules', fn() => $sched()->list());
            $router->post('/api/schedules', function ($req) use ($sched) { return $sched()->schedule($req->json()); });
            $router->delete('/api/schedules/{id}', function ($req, $id) use ($sched) { return $sched()->unschedule($id); });
            $router->get('/api/schedules/{id}/history', function ($req, $id) use ($sched) { return $sched()->history($id, (int) $req->query('limit', 20)); });
            $router->post('/api/cron/tick', fn() => $sched()->tick());
            register_shutdown_function(function () use ($sched) {
                try { $sched()->tick(); } catch (\Throwable) {}
            });
        }

        // A2I service endpoints
        if ($container->has(IntegrationManager::class)) {
            $intMgr = fn() => $container->get(IntegrationManager::class);
            $router->get('/api/services', fn() => $intMgr()->listServices());
            $router->post('/api/services', function ($req) use ($intMgr, $container) {
                $def = new ServiceDefinition($req->json());
                $agent = $container->get(AgentFacade::class);
                return $intMgr()->integrate($def, $agent);
            });
            $router->delete('/api/services/{name}', function ($req, $name) use ($intMgr) { return $intMgr()->remove($name); });
            $router->post('/api/services/{name}/test', function ($req, $name) use ($intMgr) {
                $def = $intMgr()->getService($name);
                if (!$def) return ['error' => 'Not found', 'status' => 404];
                return $intMgr()->testConnection($def);
            });
        }

        // A2P page endpoints
        if ($container->has(PageBuilder::class)) {
            $pb = fn() => $container->get(PageBuilder::class);
            $router->get('/api/pages', fn() => $pb()->list());
            $router->post('/api/pages', function ($req) use ($pb) { return $pb()->define($req->json()); });
            $router->get('/api/pages/catalog', fn() => $pb()->catalog());
            $router->get('/api/pages/{slug}', function ($req, $slug) use ($pb) {
                $params = [];
                parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
                $rendered = $pb()->render($slug, $params);
                if (!$rendered) return ['error' => 'Page not found', 'status' => 404];
                return $rendered;
            });
            $router->delete('/api/pages/{slug}', function ($req, $slug) use ($pb) { return $pb()->remove($slug); });
        }

        // A2T test endpoint
        if ($container->has(TestRunner::class)) {
            $router->post('/api/agent/test', function ($req) use ($container) {
                return $container->get(TestRunner::class)->run($req->json());
            });
        }

        // Scaffolding endpoints
        if ($container->has(Scaffolding\ScaffoldingEngine::class)) {
            $scaff = fn() => $container->get(Scaffolding\ScaffoldingEngine::class);
            $router->post('/api/scaffold', function ($req) use ($scaff) {
                $message = ($req->json())['message'] ?? '';
                if (empty($message)) return ['error' => 'Message is required'];
                return $scaff()->process($message);
            });
            $router->get('/api/scaffold', fn() => $scaff()->status());
            $router->post('/api/scaffold/reset', fn() => $scaff()->reset());
        }

        // Project management endpoints
        if ($container->has(Project\ProjectManager::class)) {
            $pm = fn() => $container->get(Project\ProjectManager::class);
            $router->get('/api/projects', fn() => $pm()->list());
            $router->post('/api/projects', function ($req) use ($pm) {
                $b = $req->json();
                return $pm()->create($b['name'] ?? '', $b['description'] ?? '');
            });
            $router->get('/api/projects/{id}', function ($req, $id) use ($pm) { return $pm()->get($id) ?? ['error' => 'Not found']; });
            $router->post('/api/projects/{id}/activate', function ($req, $id) use ($pm, $container) {
                $result = $pm()->activate($id);
                if (isset($result['error'])) return $result;
                $paths = $pm()->activePaths();
                $agent = $container->get(AgentFacade::class);
                if ($container->has(Scaffolding\ScaffoldingEngine::class)) {
                    $provider = $agent->getProvider();
                    $container->instance(Scaffolding\ScaffoldingEngine::class, new Scaffolding\ScaffoldingEngine($agent, $paths['scaffolding'], $provider));
                }
                $container->instance(PageBuilder::class, new PageBuilder($paths['pages'], $agent));
                $container->instance(IntegrationManager::class, new IntegrationManager($paths['integrations']));
                if ($container->has(WorkflowEngine::class)) {
                    $container->instance(Scheduler::class, new Scheduler($container->get(WorkflowEngine::class), $paths['schedules']));
                }
                return $result;
            });
            $router->put('/api/projects/{id}', function ($req, $id) use ($pm) { return $pm()->rename($id, ($req->json())['name'] ?? ''); });
            $router->delete('/api/projects/{id}', function ($req, $id) use ($pm) { return $pm()->delete($id); });
        }

        // Builder dashboard
        $router->get('/api/builder/status', function () use ($container) {
            $status = ['timestamp' => date('c')];
            $paths = null;
            if ($container->has(Project\ProjectManager::class)) {
                $pm = $container->get(Project\ProjectManager::class);
                $paths = $pm->activePaths();
                $status['project'] = $pm->activeId();
            }
            $pagesPath = $paths ? $paths['pages'] : 'storage/agent/pages';
            $servicesPath = $paths ? $paths['integrations'] : 'storage/agent/integrations';
            $schedulesPath = $paths ? $paths['schedules'] : 'storage/agent/schedules';
            $memoryPath = $paths ? $paths['memory'] : 'storage/agent/memory';
            $scaffoldPath = $paths ? $paths['scaffolding'] : 'storage/agent/scaffolding';

            if ($container->has(EntityMaterializer::class)) {
                $schemas = $container->get(EntityMaterializer::class)->listSchemas();
                $entities = [];
                foreach ($schemas as $name => $schema) {
                    $count = 0;
                    try { $count = count($container->get(EntityMaterializer::class)->findAll($name, [], 1000)); } catch (\Throwable) {}
                    $entities[] = ['name' => $name, 'label' => $schema['label'] ?? $name, 'fields' => count($schema['fields'] ?? []), 'records' => $count];
                }
                $status['entities'] = $entities;
            }

            $pagesFile = $pagesPath . '/pages.json';
            $status['pages'] = file_exists($pagesFile) ? array_map(fn($p) => [
                'slug' => $p['slug'] ?? '', 'title' => $p['title'] ?? '', 'template' => $p['template'] ?? '',
                'layout' => $p['layout'] ?? '', 'sections' => count($p['sections'] ?? []), 'auth' => $p['auth']['required'] ?? false,
            ], array_values(json_decode(file_get_contents($pagesFile), true) ?: [])) : [];

            $servicesFile = $servicesPath . '/services.json';
            $status['services'] = file_exists($servicesFile) ? array_values(json_decode(file_get_contents($servicesFile), true) ?: []) : [];

            $schedulesFile = $schedulesPath . '/schedules.json';
            $status['schedules'] = file_exists($schedulesFile) ? array_values(json_decode(file_get_contents($schedulesFile), true) ?: []) : [];

            $memStats = ['memories' => 0, 'skills' => 0, 'knowledge' => 0, 'sessions' => 0, 'profile' => false];
            foreach (['memories', 'skills', 'knowledge', 'sessions'] as $col) {
                $dir = $memoryPath . '/' . $col;
                if (is_dir($dir)) {
                    $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
                    foreach ($iter as $f) { if ($f->getExtension() === 'json') $memStats[$col]++; }
                }
            }
            $profileDir = $memoryPath . '/profiles';
            if (is_dir($profileDir)) {
                $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($profileDir, \FilesystemIterator::SKIP_DOTS));
                foreach ($iter as $f) { if ($f->getExtension() === 'json') { $memStats['profile'] = true; break; } }
            }
            $status['memory'] = $memStats;

            $scaffoldFile = $scaffoldPath . '/session.json';
            $status['scaffold'] = file_exists($scaffoldFile)
                ? (function () use ($scaffoldFile) {
                    $s = json_decode(file_get_contents($scaffoldFile), true) ?: [];
                    return ['state' => $s['state'] ?? 'idle', 'plan' => $s['plan'] ?? [], 'context' => $s['context'] ?? [], 'history' => count($s['history'] ?? [])];
                })()
                : ['state' => 'idle', 'plan' => [], 'context' => [], 'history' => 0];

            if ($container->has(AgentFacade::class)) {
                $status['tools'] = count($container->get(AgentFacade::class)->listTools());
            }

            return $status;
        });

        // MCP endpoint
        $router->post('/api/mcp', function ($req) use ($container) {
            $mcp = new MCPController(
                new MCPServer($container->get(AgentFacade::class))
            );
            return $mcp->handle($req);
        });
    }

    private static function createProvider(array $config): ProviderInterface
    {
        $name = $config['provider'] ?? 'ollama';
        $conf = $config['providers'][$name] ?? [];

        return match ($name) {
            'ollama' => new OllamaProvider(
                model: $conf['model'] ?? 'qwen2.5:7b',
                host: $conf['host'] ?? 'http://localhost:11434',
                temperature: (float) ($conf['temperature'] ?? 0.7),
            ),
            'cloudflare', 'workers-ai' => new WorkersAIProvider(
                accountId: $conf['account_id'] ?? ($config['cf_account_id'] ?? ''),
                apiToken: $conf['api_token'] ?? ($config['cf_api_token'] ?? ''),
                model: $conf['model'] ?? '@cf/ibm-granite/granite-4.0-h-micro',
            ),
            'openrouter' => new OpenRouterProvider(
                apiKey: $conf['api_key'] ?? '',
                model: $conf['model'] ?? 'nousresearch/hermes-4-scout',
            ),
            'openai' => new OpenAIProvider(
                apiKey: $conf['api_key'] ?? '',
                model: $conf['model'] ?? 'gpt-4o-mini',
                temperature: (float) ($conf['temperature'] ?? 0.7),
            ),
            default => new OllamaProvider(model: 'qwen2.5:7b'),
        };
    }

    private static function loadOptionalBridges(ToolRegistry $tools): void
    {
        // Shell bridge (optional: php-agent-shell)
        if (class_exists(\PHPAgentShell\AgentShell::class)) {
            try {
                foreach (\ChimeraNoWP\Agent\Bridge\ShellBridge::tools(new \PHPAgentShell\AgentShell()) as $tool) {
                    $tools->register($tool);
                }
            } catch (\Throwable) {}
        }

        // A2E bridge (optional: php-a2e)
        if (class_exists(\PHPA2E\A2E::class)) {
            try {
                foreach (\ChimeraNoWP\Agent\Bridge\A2EBridge::tools(new \PHPA2E\A2E()) as $tool) {
                    $tools->register($tool);
                }
            } catch (\Throwable) {}
        }
    }

    private static function registerExtendedTools(ToolRegistry $tools, Container $container, array $config, AgentFacade $facade): void
    {
        // A2D entity tools
        if ($container->has(EntityMaterializer::class)) {
            $mat = $container->get(EntityMaterializer::class);

            $tools->register(new ToolDefinition('define_entity', 'Define a new data entity. Creates DB table, CRUD, validation, and optional search.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string', 'description' => 'Entity name (lowercase, underscores)'],
                    'label' => ['type' => 'string'], 'description' => ['type' => 'string'],
                    'fields' => ['type' => 'array', 'description' => 'Field defs: [{name, type, required, values, target}]'],
                    'search' => ['type' => 'boolean'], 'api' => ['type' => 'boolean'],
                ], 'required' => ['entity', 'fields'],
            ], fn($a) => json_encode($mat->materialize(new EntitySchema($a))), category: 'entity'));

            $tools->register(new ToolDefinition('list_entities', 'List all data entities.', [
                'type' => 'object', 'properties' => (object)[], 'required' => [],
            ], fn($a) => json_encode($mat->listSchemas()), safe: true, category: 'entity'));

            $tools->register(new ToolDefinition('entity_insert', 'Insert a record.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string'], 'data' => ['type' => 'object'],
                ], 'required' => ['entity', 'data'],
            ], fn($a) => json_encode($mat->insert($a['entity'], $a['data'])), category: 'entity'));

            $tools->register(new ToolDefinition('entity_find', 'Find record by ID.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string'], 'id' => ['type' => 'integer'],
                ], 'required' => ['entity', 'id'],
            ], fn($a) => json_encode($mat->find($a['entity'], (int) $a['id']) ?? ['error' => 'Not found']), safe: true, category: 'entity'));

            $tools->register(new ToolDefinition('entity_list', 'List records with filters.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string'], 'filters' => ['type' => 'object'], 'limit' => ['type' => 'integer'],
                ], 'required' => ['entity'],
            ], fn($a) => json_encode($mat->findAll($a['entity'], $a['filters'] ?? [], (int) ($a['limit'] ?? 50))), safe: true, category: 'entity'));

            $tools->register(new ToolDefinition('entity_update', 'Update a record.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string'], 'id' => ['type' => 'integer'], 'data' => ['type' => 'object'],
                ], 'required' => ['entity', 'id', 'data'],
            ], fn($a) => json_encode($mat->update($a['entity'], (int) $a['id'], $a['data'])), category: 'entity'));

            $tools->register(new ToolDefinition('entity_delete', 'Delete a record.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string'], 'id' => ['type' => 'integer'],
                ], 'required' => ['entity', 'id'],
            ], fn($a) => json_encode($mat->delete($a['entity'], (int) $a['id'])), category: 'entity'));

            $tools->register(new ToolDefinition('entity_search', 'Semantic search within an entity.', [
                'type' => 'object', 'properties' => [
                    'entity' => ['type' => 'string'], 'query' => ['type' => 'string'], 'limit' => ['type' => 'integer'],
                ], 'required' => ['entity', 'query'],
            ], fn($a) => json_encode($mat->search($a['entity'], $a['query'], (int) ($a['limit'] ?? 10))), safe: true, category: 'entity'));

            // A2T test runner
            $workflowEngine = $container->has(WorkflowEngine::class) ? $container->get(WorkflowEngine::class) : new WorkflowEngine();
            $pb = $container->has(PageBuilder::class) ? $container->get(PageBuilder::class) : null;
            $runner = new TestRunner($mat, $workflowEngine, $facade, $pb);
            $container->singleton(TestRunner::class, fn() => $runner);

            $tools->register(new ToolDefinition('run_tests', 'Run declarative test suite.', [
                'type' => 'object', 'properties' => [
                    'suite' => ['type' => 'string'], 'tests' => ['type' => 'array'],
                ], 'required' => ['suite', 'tests'],
            ], fn($a) => json_encode($runner->run(['suite' => $a['suite'], 'tests' => $a['tests']])), category: 'testing'));
        }

        // A2I integration tools
        if ($container->has(IntegrationManager::class)) {
            $intMgr = $container->get(IntegrationManager::class);

            $tools->register(new ToolDefinition('integrate_service', 'Connect to external REST API.', [
                'type' => 'object', 'properties' => [
                    'service' => ['type' => 'string'], 'label' => ['type' => 'string'],
                    'base_url' => ['type' => 'string'], 'auth' => ['type' => 'object'],
                    'endpoints' => ['type' => 'array'], 'test' => ['type' => 'object'],
                ], 'required' => ['service', 'base_url', 'auth', 'endpoints'],
            ], fn($a) => json_encode($intMgr->integrate(new ServiceDefinition($a), $facade)), category: 'integration'));

            $tools->register(new ToolDefinition('list_services', 'List integrated services.', [
                'type' => 'object', 'properties' => (object)[], 'required' => [],
            ], fn($a) => json_encode($intMgr->listServices()), safe: true, category: 'integration'));

            $tools->register(new ToolDefinition('remove_service', 'Remove a service integration.', [
                'type' => 'object', 'properties' => ['service' => ['type' => 'string']], 'required' => ['service'],
            ], fn($a) => json_encode($intMgr->remove($a['service'])), category: 'integration'));
        }

        // Scheduler tools
        if ($container->has(Scheduler::class)) {
            $sched = $container->get(Scheduler::class);

            $tools->register(new ToolDefinition('schedule_workflow', 'Schedule autonomous workflow.', [
                'type' => 'object', 'properties' => [
                    'name' => ['type' => 'string'], 'steps' => ['type' => 'array'],
                    'interval' => ['type' => 'string'], 'input' => ['type' => 'object'],
                ], 'required' => ['name', 'steps', 'interval'],
            ], fn($a) => json_encode($sched->schedule($a)), category: 'scheduler'));

            $tools->register(new ToolDefinition('list_schedules', 'List scheduled workflows.', [
                'type' => 'object', 'properties' => (object)[], 'required' => [],
            ], fn($a) => json_encode($sched->list()), safe: true, category: 'scheduler'));

            $tools->register(new ToolDefinition('unschedule_workflow', 'Remove a scheduled workflow.', [
                'type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id'],
            ], fn($a) => json_encode($sched->unschedule($a['id'])), category: 'scheduler'));
        }

        // A2P page tools
        if ($container->has(PageBuilder::class)) {
            $pb = $container->get(PageBuilder::class);

            $tools->register(new ToolDefinition('define_page', 'Define a UI page with template and components.', [
                'type' => 'object', 'properties' => [
                    'page' => ['type' => 'string'], 'title' => ['type' => 'string'],
                    'template' => ['type' => 'string'], 'layout' => ['type' => 'string'],
                    'sections' => ['type' => 'array'], 'auth' => ['type' => 'object'],
                ], 'required' => ['page', 'title', 'template', 'sections'],
            ], fn($a) => json_encode($pb->define($a)), category: 'page'));

            $tools->register(new ToolDefinition('list_pages', 'List all pages.', [
                'type' => 'object', 'properties' => (object)[], 'required' => [],
            ], fn($a) => json_encode($pb->list()), safe: true, category: 'page'));

            $tools->register(new ToolDefinition('get_component_catalog', 'Get available UI components.', [
                'type' => 'object', 'properties' => (object)[], 'required' => [],
            ], fn($a) => json_encode($pb->catalog()), safe: true, category: 'page'));
        }

        // Plugin management tools
        if ($container->has(\ChimeraNoWP\Plugin\PluginManager::class)) {
            $pm = $container->get(\ChimeraNoWP\Plugin\PluginManager::class);

            $tools->register(new ToolDefinition(
                'deploy_plugin',
                'Write a PHP plugin file to plugins/{name}/{name}.php and activate it. The code must define class Plugins\\{ClassName}\\{ClassName} implementing PluginInterface. Active on next request.',
                [
                    'type' => 'object',
                    'properties' => [
                        'name'    => ['type' => 'string', 'description' => 'Plugin slug: lowercase letters, numbers, hyphens (e.g. "invoice-generator")'],
                        'code'    => ['type' => 'string', 'description' => 'Full PHP source of the plugin file'],
                    ],
                    'required' => ['name', 'code'],
                ],
                function ($a) use ($pm) {
                    $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($a['name']));
                    $dir  = (defined('BASE_PATH') ? BASE_PATH : getcwd()) . '/plugins/' . $name;

                    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                        return json_encode(['error' => "Could not create directory: {$dir}"]);
                    }

                    $file = $dir . '/' . $name . '.php';
                    if (file_put_contents($file, $a['code']) === false) {
                        return json_encode(['error' => "Could not write file: {$file}"]);
                    }

                    // Load and activate within this request
                    $pm->loadPlugins();
                    $activated = $pm->activatePlugin($name);
                    $errors    = $pm->getPluginErrors($name);

                    return json_encode([
                        'plugin'    => $name,
                        'file'      => $file,
                        'activated' => $activated,
                        'errors'    => $errors,
                        'note'      => $activated ? 'Plugin active. Routes registered on next request.' : 'Deployed but not activated — check errors.',
                    ]);
                },
                category: 'plugin'
            ));

            $tools->register(new ToolDefinition(
                'list_plugins',
                'List all loaded plugins and their activation status.',
                ['type' => 'object', 'properties' => (object)[], 'required' => []],
                function ($a) use ($pm) {
                    $active = $pm->getActivePlugins();
                    $result = [];
                    foreach ($pm->getPlugins() as $name => $plugin) {
                        $result[] = [
                            'name'    => $name,
                            'label'   => $plugin->getName(),
                            'version' => $plugin->getVersion(),
                            'active'  => in_array($name, $active),
                            'errors'  => $pm->getPluginErrors($name),
                        ];
                    }
                    return json_encode(['plugins' => $result, 'total' => count($result)]);
                },
                safe: true,
                category: 'plugin'
            ));

            $tools->register(new ToolDefinition(
                'deactivate_plugin',
                'Deactivate an active plugin.',
                [
                    'type' => 'object',
                    'properties' => ['name' => ['type' => 'string', 'description' => 'Plugin slug']],
                    'required' => ['name'],
                ],
                fn($a) => json_encode(['deactivated' => $pm->deactivatePlugin($a['name']), 'plugin' => $a['name']]),
                category: 'plugin'
            ));
        }
    }
}
