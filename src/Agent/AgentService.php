<?php

declare(strict_types=1);

namespace Framework\Agent;

use Framework\Agent\Provider\AIProviderInterface;
use Framework\Agent\Tools\Tool;
use Framework\Agent\Workflow\WorkflowEngine;
use Framework\Agent\Memory\MemoryService;

/**
 * Agent Service — agentic loop with tools, workflows, and memory.
 *
 * The agent can:
 * 1. Chat with tools (tool call → execute → respond)
 * 2. Execute multi-step workflows (A2E pattern)
 * 3. Remember across sessions (semantic memory)
 * 4. Search site content (RAG via SearchService)
 *
 * Usage:
 *   $agent = new AgentService($provider, $tools, $workflow, $memory);
 *   $response = $agent->chat('Find posts about PHP and summarize them');
 */
class AgentService
{
    private AIProviderInterface $provider;
    private WorkflowEngine $workflow;
    private ?MemoryService $memory;
    private string $systemPrompt;
    private array $chatHistory = [];
    private string $agentId;

    /** @var Tool[] */
    private array $tools = [];

    private const MAX_TOOL_ROUNDS = 10;

    public function __construct(
        AIProviderInterface $provider,
        ?WorkflowEngine $workflow = null,
        ?MemoryService $memory = null,
        string $systemPrompt = '',
        string $agentId = 'default',
    ) {
        $this->provider     = $provider;
        $this->workflow     = $workflow ?? new WorkflowEngine();
        $this->memory       = $memory;
        $this->systemPrompt = $systemPrompt;
        $this->agentId      = $agentId;
    }

    /**
     * Register tools the agent can use.
     */
    public function addTool(Tool $tool): self
    {
        $this->tools[$tool->getName()] = $tool;
        $this->workflow->registerTool($tool);
        return $this;
    }

    /**
     * @param Tool[] $tools
     */
    public function addTools(array $tools): self
    {
        foreach ($tools as $tool) {
            $this->addTool($tool);
        }
        return $this;
    }

    /**
     * Chat with the agent. Handles tool call loops automatically.
     *
     * @param string $message User message.
     * @return string Agent response.
     */
    public function chat(string $message): string
    {
        $this->chatHistory[] = ['role' => 'user', 'content' => $message];

        // Inject relevant memories into context
        $systemPrompt = $this->buildSystemPrompt($message);

        $toolSchemas = array_map(fn(Tool $t) => $t->toSchema(), array_values($this->tools));

        // Agentic loop: chat → tool calls → results → chat → ...
        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->provider->chat($this->chatHistory, $systemPrompt, $toolSchemas);

            // If no tool calls, we have the final response
            if (empty($response['tool_calls'])) {
                $content = $response['content'] ?? '';
                $this->chatHistory[] = ['role' => 'assistant', 'content' => $content];
                return $content;
            }

            // Execute tool calls
            $this->chatHistory[] = [
                'role'       => 'assistant',
                'content'    => $response['content'] ?? null,
                'tool_calls' => $response['tool_calls'],
            ];

            foreach ($response['tool_calls'] as $call) {
                $fn   = $call['function'] ?? $call;
                $name = $fn['name'] ?? '';
                $args = json_decode($fn['arguments'] ?? '{}', true) ?: [];

                $result = $this->executeTool($name, $args);

                $this->chatHistory[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'] ?? $name,
                    'content'      => is_string($result) ? $result : json_encode($result),
                ];
            }
        }

        return '[Agent reached maximum tool call rounds]';
    }

    /**
     * Execute a workflow.
     *
     * @param array      $steps   Workflow steps.
     * @param array|null $initial Initial data.
     * @return array Workflow result with data store.
     */
    public function runWorkflow(array $steps, ?array $initial = null): array
    {
        return $this->workflow->run($steps, $initial);
    }

    /**
     * Remember something for future sessions.
     */
    public function remember(string $content, string $type = 'fact', array $tags = []): ?string
    {
        if (!$this->memory) return null;
        $result = $this->memory->saveMemory($this->agentId, 'default', $content, $type, $tags);
        return $result['id'] ?? null;
    }

    /**
     * Recall relevant memories.
     */
    public function recall(string $query, int $limit = 5): array
    {
        if (!$this->memory) return [];
        return $this->memory->simpleRecall($this->agentId, $query, $limit);
    }

    /**
     * List all registered tools with their schemas.
     */
    public function listTools(): array
    {
        $tools = array_map(fn(Tool $t) => $t->toSchema(), array_values($this->tools));

        $builtins = [
            ['name' => 'remember', 'description' => 'Save a memory for future sessions.', 'parameters' => ['type' => 'object', 'properties' => ['content' => ['type' => 'string', 'description' => 'What to remember'], 'type' => ['type' => 'string', 'description' => 'Memory type: fact, preference, correction, event'], 'tags' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['content']]],
            ['name' => 'recall', 'description' => 'Recall relevant memories by semantic similarity.', 'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'What to remember about'], 'limit' => ['type' => 'integer', 'description' => 'Max memories to return']], 'required' => ['query']]],
            ['name' => 'run_workflow', 'description' => 'Execute a multi-step workflow with data store.', 'parameters' => ['type' => 'object', 'properties' => ['steps' => ['type' => 'array', 'description' => 'Workflow steps'], 'input' => ['type' => 'object', 'description' => 'Initial data']], 'required' => ['steps']]],
        ];

        return array_merge($tools, $builtins);
    }

    /**
     * Execute a tool by name (public access for REST API).
     */
    public function invokeToolByName(string $name, array $args = []): mixed
    {
        return $this->executeTool($name, $args);
    }

    /**
     * Get chat history.
     */
    public function history(): array
    {
        return $this->chatHistory;
    }

    /**
     * Clear chat history (start new conversation).
     */
    public function reset(): void
    {
        $this->chatHistory = [];
    }

    // ── Private ──────────────────────────────────────────────────────

    private function executeTool(string $name, array $args): mixed
    {
        // Built-in tools
        if ('remember' === $name) {
            return $this->remember($args['content'] ?? '', $args['type'] ?? 'fact', $args['tags'] ?? []);
        }
        if ('recall' === $name) {
            return $this->recall($args['query'] ?? '', (int)($args['limit'] ?? 5));
        }
        if ('run_workflow' === $name) {
            return $this->runWorkflow($args['steps'] ?? [], $args['input'] ?? null);
        }

        // User-defined tools
        $tool = $this->tools[$name] ?? null;
        if (!$tool) {
            return ['error' => "Tool '{$name}' not found."];
        }

        try {
            return $tool->execute($args);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function buildSystemPrompt(string $currentMessage): string
    {
        $prompt = $this->systemPrompt;

        // Inject relevant memories via unified recall
        if ($this->memory) {
            $context = $this->memory->recall($this->agentId, 'default', $currentMessage, 5, 4000);
            $formatted = $context['formatted'] ?? '';
            if (!empty($formatted)) {
                $prompt .= "\n\n" . $formatted;
            }
        }

        return $prompt;
    }
}
