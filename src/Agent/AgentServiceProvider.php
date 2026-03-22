<?php

declare(strict_types=1);

namespace Framework\Agent;

use Framework\Agent\Provider\OllamaProvider;
use Framework\Agent\Provider\HttpProvider;
use Framework\Agent\Tools\Tool;
use Framework\Agent\Workflow\WorkflowEngine;
use Framework\Agent\Memory\MemoryService;
use Framework\Agent\MCP\MCPServer;
use Framework\Agent\MCP\MCPController;
use Framework\Agent\Data\EntitySchema;
use Framework\Agent\Data\EntityMaterializer;
use Framework\Agent\Testing\TestRunner;
use Framework\Agent\Integration\ServiceDefinition;
use Framework\Agent\Integration\IntegrationManager;
use Framework\Agent\Workflow\Scheduler;
use Framework\Agent\Page\PageBuilder;
use Framework\Agent\Page\ComponentCatalog;
use Framework\Core\Container;
use Framework\Core\Router;
use Framework\Search\SearchService;
use Framework\Content\ContentRepository;
use Framework\Content\ContentService;
use Framework\Database\Connection;

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
        $container->singleton(WorkflowEngine::class, fn() => $workflow);

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
        $integrations->bootTools($agent); // re-register tools from stored services
        $container->singleton(IntegrationManager::class, fn() => $integrations);

        // Build Scheduler (pseudo-cron for autonomous workflows)
        $schedulerPath = $config['scheduler_path'] ?? 'storage/agent/schedules';
        $scheduler = new Scheduler($workflow, $schedulerPath);
        $container->singleton(Scheduler::class, fn() => $scheduler);

        // Build A2P page builder
        $pagePath = $config['pages_path'] ?? 'storage/agent/pages';
        $pageBuilder = new PageBuilder($pagePath, $agent);
        $container->singleton(PageBuilder::class, fn() => $pageBuilder);

        // Build Scaffolding Engine (conversational system builder)
        $scaffoldPath = $config['scaffolding_path'] ?? 'storage/agent/scaffolding';
        $scaffolding = new Scaffolding\ScaffoldingEngine($agent, $scaffoldPath);
        $container->singleton(Scaffolding\ScaffoldingEngine::class, fn() => $scaffolding);

        // Register built-in tools (AFTER all A2 components are in the container)
        self::registerBuiltinTools($agent, $container, $config);

        $container->singleton(AgentService::class, fn() => $agent);
    }

    public static function registerRoutes(Router $router, Container $container): void
    {
        $make = fn() => new AgentController($container->get(AgentService::class));

        $router->get('/api/agent/tools', fn() => $make()->listTools());
        $router->post('/api/agent/tools/{name}', fn($req, $name) => $make()->executeTool($req, $name));
        $router->post('/api/agent/chat', fn($req) => $make()->chat($req));
        $router->post('/api/agent/workflow', fn($req) => $make()->workflow($req));
        $router->post('/api/agent/memory', fn($req) => $make()->saveMemory($req));
        $router->get('/api/agent/memory', fn($req) => $make()->recallMemory($req));
        $router->post('/api/agent/reset', fn() => $make()->reset());

        // A2D entity endpoints (auto-generated CRUD for materialized entities)
        if ($container->has(EntityMaterializer::class)) {
            $mat = fn() => $container->get(EntityMaterializer::class);

            $router->get('/api/entities', fn() => $mat()->listSchemas());

            $router->post('/api/entities', function ($req) use ($mat) {
                $def = $req->json();
                $schema = new EntitySchema($def);
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

        // Scheduler endpoints + pseudo-cron tick
        if ($container->has(Scheduler::class)) {
            $sched = fn() => $container->get(Scheduler::class);

            $router->get('/api/schedules', fn() => $sched()->list());

            $router->post('/api/schedules', function ($req) use ($sched) {
                return $sched()->schedule($req->json());
            });

            $router->delete('/api/schedules/{id}', function ($req, $id) use ($sched) {
                return $sched()->unschedule($id);
            });

            $router->get('/api/schedules/{id}/history', function ($req, $id) use ($sched) {
                return $sched()->history($id, (int) $req->query('limit', 20));
            });

            $router->post('/api/cron/tick', fn() => $sched()->tick());

            // Pseudo-cron: tick on every request (lightweight check)
            register_shutdown_function(function () use ($sched) {
                try {
                    $sched()->tick();
                } catch (\Throwable) {
                    // Never break the main request
                }
            });
        }

        // A2I service endpoints
        if ($container->has(IntegrationManager::class)) {
            $intMgr = fn() => $container->get(IntegrationManager::class);

            $router->get('/api/services', fn() => $intMgr()->listServices());

            $router->post('/api/services', function ($req) use ($intMgr, $container) {
                $def = new ServiceDefinition($req->json());
                $agent = $container->get(AgentService::class);
                return $intMgr()->integrate($def, $agent);
            });

            $router->delete('/api/services/{name}', function ($req, $name) use ($intMgr) {
                return $intMgr()->remove($name);
            });

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

            $router->post('/api/pages', function ($req) use ($pb) {
                return $pb()->define($req->json());
            });

            $router->get('/api/pages/catalog', fn() => $pb()->catalog());

            $router->get('/api/pages/{slug}', function ($req, $slug) use ($pb) {
                // Extract params from query string (e.g., ?id=42)
                $params = [];
                if (method_exists($req, 'queryAll')) {
                    $params = $req->queryAll();
                } else {
                    parse_str($_SERVER['QUERY_STRING'] ?? '', $params);
                }
                $rendered = $pb()->render($slug, $params);
                if (!$rendered) return ['error' => 'Page not found', 'status' => 404];
                return $rendered;
            });

            $router->delete('/api/pages/{slug}', function ($req, $slug) use ($pb) {
                return $pb()->remove($slug);
            });
        }

        // A2T test endpoint
        if ($container->has(TestRunner::class)) {
            $router->post('/api/agent/test', function ($req) use ($container) {
                $body = $req->json();
                $runner = $container->get(TestRunner::class);
                return $runner->run($body);
            });
        }

        // Scaffolding endpoints (conversational system builder)
        if ($container->has(Scaffolding\ScaffoldingEngine::class)) {
            $scaff = fn() => $container->get(Scaffolding\ScaffoldingEngine::class);

            $router->post('/api/scaffold', function ($req) use ($scaff) {
                $body = $req->json();
                $message = $body['message'] ?? '';
                if (empty($message)) return ['error' => 'Message is required'];
                return $scaff()->process($message);
            });

            $router->get('/api/scaffold', fn() => $scaff()->status());

            $router->post('/api/scaffold/reset', fn() => $scaff()->reset());
        }

        // MCP endpoint
        $router->post('/api/mcp', function ($req) use ($container) {
            $mcp = new MCPController(
                new MCPServer($container->get(AgentService::class))
            );
            return $mcp->handle($req);
        });
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

        // A2D tools — agent can define and manage data entities
        if ($container->has(EntityMaterializer::class)) {
            $mat = $container->get(EntityMaterializer::class);

            $agent->addTool(
                Tool::make('define_entity', 'Define a new data entity. Creates the database table, CRUD operations, validation, and optional search index. Use this when you need to store a new type of structured data.')
                    ->param('entity', 'string', 'Entity name (lowercase, underscores)', true)
                    ->param('label', 'string', 'Human-readable label')
                    ->param('description', 'string', 'What this entity represents')
                    ->param('fields', 'array', 'Array of field definitions: [{name, type, required, values, target}]', true)
                    ->param('search', 'boolean', 'Enable semantic search on this entity (default false)')
                    ->param('api', 'boolean', 'Generate REST API endpoints (default true)')
                    ->handler(function ($entity, $label = '', $description = '', $fields = [], $search = false, $api = true) use ($mat) {
                        $schema = new EntitySchema([
                            'entity' => $entity, 'label' => $label,
                            'description' => $description, 'fields' => $fields,
                            'search' => $search, 'api' => $api,
                        ]);
                        return $mat->materialize($schema);
                    })
            );

            $agent->addTool(
                Tool::make('list_entities', 'List all defined data entities with their schemas.')
                    ->handler(fn() => $mat->listSchemas())
            );

            $agent->addTool(
                Tool::make('entity_insert', 'Insert a record into a data entity.')
                    ->param('entity', 'string', 'Entity name', true)
                    ->param('data', 'object', 'Record data as key-value pairs', true)
                    ->handler(fn($entity, $data) => $mat->insert($entity, $data))
            );

            $agent->addTool(
                Tool::make('entity_find', 'Find a record by ID in a data entity.')
                    ->param('entity', 'string', 'Entity name', true)
                    ->param('id', 'integer', 'Record ID', true)
                    ->handler(fn($entity, $id) => $mat->find($entity, (int)$id) ?? ['error' => 'Not found'])
            );

            $agent->addTool(
                Tool::make('entity_list', 'List records from a data entity with optional filters.')
                    ->param('entity', 'string', 'Entity name', true)
                    ->param('filters', 'object', 'Filter conditions as key-value pairs')
                    ->param('limit', 'integer', 'Max records (default 50)')
                    ->handler(fn($entity, $filters = [], $limit = 50) => $mat->findAll($entity, $filters, (int)$limit))
            );

            $agent->addTool(
                Tool::make('entity_update', 'Update a record in a data entity.')
                    ->param('entity', 'string', 'Entity name', true)
                    ->param('id', 'integer', 'Record ID', true)
                    ->param('data', 'object', 'Fields to update', true)
                    ->handler(fn($entity, $id, $data) => $mat->update($entity, (int)$id, $data))
            );

            $agent->addTool(
                Tool::make('entity_delete', 'Delete a record from a data entity.')
                    ->param('entity', 'string', 'Entity name', true)
                    ->param('id', 'integer', 'Record ID', true)
                    ->handler(fn($entity, $id) => $mat->delete($entity, (int)$id))
            );

            $agent->addTool(
                Tool::make('entity_search', 'Semantic search within a data entity. Only works if entity has search enabled.')
                    ->param('entity', 'string', 'Entity name', true)
                    ->param('query', 'string', 'Natural language search query', true)
                    ->param('limit', 'integer', 'Max results (default 10)')
                    ->handler(fn($entity, $query, $limit = 10) => $mat->search($entity, $query, (int)$limit))
            );

            // A2T tool — agent can run declarative tests
            $workflowEngine = $container->has(WorkflowEngine::class)
                ? $container->get(WorkflowEngine::class)
                : new WorkflowEngine();
            $pb = $container->has(PageBuilder::class) ? $container->get(PageBuilder::class) : null;
            $runner = new TestRunner($mat, $workflowEngine, $agent, $pb);
            $container->singleton(TestRunner::class, fn() => $runner);

            $agent->addTool(
                Tool::make('run_tests', 'Run a declarative test suite to verify entity schemas, CRUD operations, validation rules, and workflows. Use after defining entities or workflows to verify they work correctly.')
                    ->param('suite', 'string', 'Test suite name', true)
                    ->param('tests', 'array', 'Array of test assertions: [{assert, entity, data, expect, ...}]', true)
                    ->handler(fn($suite, $tests) => $runner->run(['suite' => $suite, 'tests' => $tests]))
            );
        }

        // A2I tools — agent can define external service integrations
        if ($container->has(IntegrationManager::class)) {
            $intMgr = $container->get(IntegrationManager::class);

            $agent->addTool(
                Tool::make('integrate_service', 'Connect to an external REST API service. Creates stored credentials and one tool per endpoint. Use when you need to interact with a third-party API like Stripe, GitHub, Slack, etc.')
                    ->param('service', 'string', 'Service name (lowercase, underscores)', true)
                    ->param('label', 'string', 'Human-readable label')
                    ->param('base_url', 'string', 'API base URL (e.g. https://api.stripe.com/v1)', true)
                    ->param('auth', 'object', 'Auth config: {type: "bearer", key: "sk_...", key_env: "STRIPE_KEY"}', true)
                    ->param('endpoints', 'array', 'Array of endpoints: [{name, method, path, params, body, description}]', true)
                    ->param('test', 'object', 'Connection test: {endpoint: "name", expect_status: 200}')
                    ->handler(function ($service, $label = '', $base_url = '', $auth = [], $endpoints = [], $test = []) use ($intMgr, $agent) {
                        $def = new ServiceDefinition([
                            'service' => $service, 'label' => $label,
                            'base_url' => $base_url, 'auth' => $auth,
                            'endpoints' => $endpoints, 'test' => $test,
                        ]);
                        return $intMgr->integrate($def, $agent);
                    })
            );

            $agent->addTool(
                Tool::make('list_services', 'List all integrated external services.')
                    ->handler(fn() => $intMgr->listServices())
            );

            $agent->addTool(
                Tool::make('remove_service', 'Remove an external service integration.')
                    ->param('service', 'string', 'Service name to remove', true)
                    ->handler(fn($service) => $intMgr->remove($service))
            );

            $agent->addTool(
                Tool::make('test_service', 'Test connection to an integrated service.')
                    ->param('service', 'string', 'Service name to test', true)
                    ->handler(function ($service) use ($intMgr) {
                        $def = $intMgr->getService($service);
                        if (!$def) return ['error' => "Service '{$service}' not found"];
                        return $intMgr->testConnection($def);
                    })
            );
        }

        // Scheduler tools — autonomous workflow execution
        if ($container->has(Scheduler::class)) {
            $sched = $container->get(Scheduler::class);

            $agent->addTool(
                Tool::make('schedule_workflow', 'Schedule a workflow to run automatically on an interval. Makes the agent autonomous — it can act without being asked.')
                    ->param('id', 'string', 'Schedule ID (unique name)')
                    ->param('name', 'string', 'Human-readable name', true)
                    ->param('steps', 'array', 'Workflow steps to execute', true)
                    ->param('interval', 'string', 'Interval: every_minute, every_5min, hourly, daily, weekly, or Nm/Nh/Nd', true)
                    ->param('input', 'object', 'Initial data for each run')
                    ->param('enabled', 'boolean', 'Start immediately (default true)')
                    ->handler(fn($id = null, $name = '', $steps = [], $interval = 'hourly', $input = null, $enabled = true) =>
                        $sched->schedule([
                            'id' => $id, 'name' => $name, 'steps' => $steps,
                            'interval' => $interval, 'input' => $input, 'enabled' => $enabled,
                        ])
                    )
            );

            $agent->addTool(
                Tool::make('list_schedules', 'List all scheduled workflows with their status and next run time.')
                    ->handler(fn() => $sched->list())
            );

            $agent->addTool(
                Tool::make('unschedule_workflow', 'Stop and remove a scheduled workflow.')
                    ->param('id', 'string', 'Schedule ID to remove', true)
                    ->handler(fn($id) => $sched->unschedule($id))
            );

            $agent->addTool(
                Tool::make('pause_schedule', 'Pause or resume a scheduled workflow.')
                    ->param('id', 'string', 'Schedule ID', true)
                    ->param('enabled', 'boolean', 'true to resume, false to pause', true)
                    ->handler(fn($id, $enabled) => $sched->setEnabled($id, (bool)$enabled))
            );

            $agent->addTool(
                Tool::make('schedule_history', 'Get execution history for a scheduled workflow.')
                    ->param('id', 'string', 'Schedule ID', true)
                    ->param('limit', 'integer', 'Max entries (default 20)')
                    ->handler(fn($id, $limit = 20) => $sched->history($id, (int)$limit))
            );
        }

        // A2P tools — agent can define pages and UI
        if ($container->has(PageBuilder::class)) {
            $pb = $container->get(PageBuilder::class);

            $agent->addTool(
                Tool::make('define_page', 'Define a page using template, layout, and components from the catalog. Creates a complete UI page that the frontend can render.')
                    ->param('page', 'string', 'Page slug (URL path)', true)
                    ->param('title', 'string', 'Page title', true)
                    ->param('template', 'string', 'Template: dashboard, list-page, detail-page, form-page, login-page, landing-page, settings-page, error-page', true)
                    ->param('layout', 'string', 'Layout: admin-sidebar, public-centered, fullwidth, split, stacked')
                    ->param('sections', 'array', 'Array of sections: [{slot, component, props}]', true)
                    ->param('auth', 'object', 'Auth config: {required: true, role: "admin"}')
                    ->param('description', 'string', 'Page description')
                    ->handler(fn($page, $title = '', $template = 'dashboard', $layout = 'admin-sidebar', $sections = [], $auth = [], $description = '') =>
                        $pb->define([
                            'page' => $page, 'title' => $title, 'template' => $template,
                            'layout' => $layout, 'sections' => $sections,
                            'auth' => $auth, 'description' => $description,
                        ])
                    )
            );

            $agent->addTool(
                Tool::make('list_pages', 'List all defined pages.')
                    ->handler(fn() => $pb->list())
            );

            $agent->addTool(
                Tool::make('remove_page', 'Remove a page definition.')
                    ->param('page', 'string', 'Page slug to remove', true)
                    ->handler(fn($page) => $pb->remove($page))
            );

            $agent->addTool(
                Tool::make('get_component_catalog', 'Get the full component catalog — all available atoms, molecules, organisms, templates, and layouts the agent can use to build pages.')
                    ->handler(fn() => $pb->catalog())
            );
        }
    }
}
