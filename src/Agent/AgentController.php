<?php

declare(strict_types=1);

namespace Framework\Agent;

use Framework\Core\Request;

/**
 * Agent REST API Controller.
 *
 * Endpoints:
 *   POST /api/agent/chat              Chat with the agent
 *   POST /api/agent/workflow           Execute a workflow
 *   POST /api/agent/memory             Save a memory
 *   GET  /api/agent/memory?q=text      Recall memories
 *   GET  /api/agent/memory/list        List all memories
 */
class AgentController
{
    public function __construct(
        private AgentService $agent,
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
     * POST /api/agent/reset
     */
    public function reset(): array
    {
        $this->agent->reset();
        return ['reset' => true];
    }
}
