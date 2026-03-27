<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent;

use ChimeraNoWP\Agent\Core\AgentLoop;
use ChimeraNoWP\Agent\Core\EventEmitter;
use ChimeraNoWP\Agent\Core\ToolDefinition;
use ChimeraNoWP\Agent\Core\ToolRegistry;
use ChimeraNoWP\Agent\LLM\Message;
use ChimeraNoWP\Agent\LLM\ProviderInterface;
use ChimeraNoWP\Agent\Memory\ContextBuilder;
use ChimeraNoWP\Agent\Memory\LearningLoop;
use ChimeraNoWP\Agent\Memory\MemoryService;
use ChimeraNoWP\Agent\Memory\SessionStore;
use ChimeraNoWP\Agent\Workflow\WorkflowEngine;

/**
 * Unified agent facade — replaces both NoWP's AgentService and Chimera's Chimera.php.
 *
 * Combines Chimera's AgentLoop/AntiLoop/ToolRegistry/EventEmitter engine
 * with NoWP's MemoryService, WorkflowEngine, and CMS integration.
 */
final class AgentFacade
{
    public readonly ToolRegistry $tools;
    public readonly EventEmitter $events;
    public readonly ?SessionStore $sessions;

    private readonly ContextBuilder $contextBuilder;
    private readonly LearningLoop $learner;

    /** @var Message[] */
    private array $history = [];

    private string $currentSessionId;

    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly ?MemoryService $memory = null,
        private readonly ?WorkflowEngine $workflowEngine = null,
        ?ToolRegistry $tools = null,
        ?EventEmitter $events = null,
        ?SessionStore $sessions = null,
        string $systemPrompt = '',
        string $agentId = 'chimera',
        string $userId = 'default',
        private readonly int $maxIterations = 25,
    ) {
        $this->tools = $tools ?? new ToolRegistry();
        $this->events = $events ?? new EventEmitter();
        $this->sessions = $sessions;

        $this->contextBuilder = new ContextBuilder(
            basePrompt: $systemPrompt,
            memoryService: $this->memory,
            agentId: $agentId,
            userId: $userId,
        );

        $this->learner = new LearningLoop(
            llm: $this->provider,
            memoryService: $this->memory,
            agentId: $agentId,
            userId: $userId,
        );

        $this->currentSessionId = $this->sessions
            ? $this->sessions->createSession()
            : uniqid('session_', true);
    }

    /**
     * Main agentic chat — runs AgentLoop with tools, memory recall, and post-conversation learning.
     *
     * @return array{content: string, iterations: int, totalTokens: int, toolsUsed: string[], learned: array}
     */
    public function chat(string $message): array
    {
        // Build context-augmented messages (system prompt + memory + trimmed history + user message)
        $messages = $this->contextBuilder->build($this->history, $message);

        // Run the agent loop
        $loop = new AgentLoop(
            provider: $this->provider,
            tools: $this->tools,
            events: $this->events,
            maxIterations: $this->maxIterations,
        );

        $result = $loop->run($messages);

        // Update conversation history (skip system prompt, keep user + assistant + tool messages)
        $this->history[] = Message::user($message);
        if (!empty($result['content'])) {
            $this->history[] = Message::assistant($result['content']);
        }

        // Save session to SQLite if available
        if ($this->sessions) {
            try {
                $this->sessions->saveMessages($this->currentSessionId, $result['messages'] ?? $messages);
            } catch (\Throwable) {}
        }

        // Post-conversation learning (best-effort)
        $learned = ['sessionSaved' => false, 'memoriesExtracted' => 0, 'skillsExtracted' => 0];
        $usedTools = !empty($result['toolsUsed']);
        if ($usedTools) {
            try {
                $allMessages = $result['messages'] ?? $messages;
                $learned = $this->learner->learn($allMessages, $usedTools);
            } catch (\Throwable) {}
        }

        return [
            'content' => $result['content'],
            'iterations' => $result['iterations'],
            'totalTokens' => $result['totalTokens'],
            'toolsUsed' => $result['toolsUsed'],
            'learned' => $learned,
        ];
    }

    /**
     * Clear conversation history.
     */
    public function clear(): void
    {
        $this->history = [];
    }

    /**
     * Get current conversation history.
     * @return Message[]
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * List all registered tools.
     */
    public function listTools(): array
    {
        $result = [];
        foreach ($this->tools->toOpenAI() as $tool) {
            $result[] = $tool;
        }
        return $result;
    }

    /**
     * Invoke a specific tool by name (for direct API access).
     */
    public function invokeToolByName(string $name, array $args = []): string
    {
        $tool = $this->tools->get($name);
        if (!$tool) {
            return json_encode(['error' => "Tool '{$name}' not found"]);
        }
        return $tool->execute($args);
    }

    /**
     * Run a workflow (A2E).
     */
    public function runWorkflow(array $steps, ?array $initialData = null): array
    {
        if (!$this->workflowEngine) {
            return ['error' => 'Workflow engine not available'];
        }
        return $this->workflowEngine->run($steps, $initialData);
    }

    /**
     * Save to memory (backwards compatible).
     */
    public function remember(string $content, string $category = 'fact', array $tags = []): ?string
    {
        if (!$this->memory) return null;
        $result = $this->memory->saveMemory('chimera', 'default', $content, $category, $tags);
        return $result['id'] ?? null;
    }

    /**
     * Recall from memory (backwards compatible).
     */
    public function recall(string $query, int $limit = 10): array
    {
        if (!$this->memory) return [];
        return $this->memory->recall('chimera', 'default', $query, maxItems: $limit);
    }

    /**
     * Get the LLM provider.
     */
    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }
}
