<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent;

use ChimeraNoWP\Core\Request;

/**
 * Agent REST API Controller.
 *
 * Endpoints:
 *   GET  /api/agent/tools              List available tools with schemas
 *   POST /api/agent/tools/{name}       Execute a tool directly
 *   POST /api/agent/chat               Chat with the agent
 *   POST /api/agent/workflow            Execute a workflow
 *   POST /api/agent/memory              Save a memory
 *   GET  /api/agent/memory?q=text       Recall memories
 */
class AgentController
{
    public function __construct(
        private AgentFacade $agent,
    ) {}

    /**
     * POST /api/agent/chat
     * Body: {"message": "Find posts about PHP"}
     */
    public function chat(Request $request): array
    {
        $body    = $request->json();
        $message = $body['message'] ?? '';

        if ('' === $message) {
            return ['error' => 'Message is required.', 'status' => 400];
        }

        $response = $this->agent->chat($message);

        return [
            'response' => $response,
            'history'  => count($this->agent->history()),
        ];
    }

    /**
     * POST /api/agent/workflow
     * Body: {"steps": [...], "input": {...}}
     */
    public function workflow(Request $request): array
    {
        $body  = $request->json();
        $steps = $body['steps'] ?? [];
        $input = $body['input'] ?? null;

        if (empty($steps)) {
            return ['error' => 'Steps are required.', 'status' => 400];
        }

        return $this->agent->runWorkflow($steps, $input);
    }

    /**
     * POST /api/agent/memory
     * Body: {"content": "User prefers dark mode", "type": "preference", "tags": ["ui"]}
     */
    public function saveMemory(Request $request): array
    {
        $body = $request->json();
        $content = $body['content'] ?? '';

        if ('' === $content) {
            return ['error' => 'Content is required.', 'status' => 400];
        }

        $id = $this->agent->remember(
            $content,
            $body['type'] ?? 'fact',
            $body['tags'] ?? []
        );

        return ['id' => $id, 'saved' => true];
    }

    /**
     * GET /api/agent/memory?q=text&limit=5
     */
    public function recallMemory(Request $request): array
    {
        $query = $request->query('q', '');
        $limit = min((int) $request->query('limit', 5), 20);

        if ('' === $query) {
            return ['error' => 'Query parameter "q" is required.', 'status' => 400];
        }

        return [
            'query'    => $query,
            'memories' => $this->agent->recall($query, $limit),
        ];
    }

    /**
     * GET /api/agent/memory/list?type=preference
     */
    public function listMemory(Request $request): array
    {
        $type = $request->query('type');

        return [
            'memories' => $this->agent->recall('', 100), // TODO: use list instead
        ];
    }

    /**
     * GET /api/agent/tools
     * Returns all available tools with their schemas.
     */
    public function listTools(): array
    {
        return [
            'tools' => $this->agent->listTools(),
            'count' => count($this->agent->listTools()),
        ];
    }

    /**
     * POST /api/agent/tools/{name}
     * Execute a single tool by name.
     * Body: {"arg1": "value1", "arg2": "value2"}
     */
    public function executeTool(Request $request, string $toolName): array
    {
        $args = $request->json() ?: [];

        $tools = $this->agent->listTools();
        $exists = false;
        foreach ($tools as $t) {
            if (($t['function']['name'] ?? $t['name'] ?? '') === $toolName) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return ['error' => "Tool '{$toolName}' not found.", 'status' => 404];
        }

        $result = $this->agent->invokeToolByName($toolName, $args);

        return [
            'tool'   => $toolName,
            'result' => $result,
        ];
    }

    /**
     * POST /api/agent/reset
     */
    public function reset(): array
    {
        $this->agent->clear();
        return ['reset' => true];
    }
}
