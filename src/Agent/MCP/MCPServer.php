<?php

/**
 * MCP Server — Model Context Protocol implementation for NoWP.
 *
 * Exposes NoWP tools as MCP tools, enabling Claude, Cursor, Windsurf,
 * and any MCP client to interact with the site.
 *
 * Protocol: JSON-RPC 2.0 over HTTP (Streamable HTTP transport)
 *
 * Methods:
 *   initialize           → server info + capabilities
 *   tools/list           → available tools with schemas
 *   tools/call           → execute a tool
 *
 * @see https://modelcontextprotocol.io/specification
 */

declare(strict_types=1);

namespace Framework\Agent\MCP;

use Framework\Agent\AgentService;

class MCPServer
{
    private const PROTOCOL_VERSION = '2025-03-26';
    private const SERVER_NAME     = 'nowp-mcp';
    private const SERVER_VERSION  = '0.1.0';

    private AgentService $agent;

    public function __construct(AgentService $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Handle an incoming MCP request (JSON-RPC 2.0).
     *
     * @param string $body Raw request body.
     * @return string JSON-RPC response.
     */
    public function handle(string $body): string
    {
        $request = json_decode($body, true);

        if (!$request || !isset($request['method'])) {
            return $this->error(null, -32600, 'Invalid Request');
        }

        $id     = $request['id'] ?? null;
        $method = $request['method'];
        $params = $request['params'] ?? [];

        $result = match ($method) {
            'initialize'     => $this->initialize($params),
            'initialized'    => null, // notification, no response needed
            'tools/list'     => $this->toolsList(),
            'tools/call'     => $this->toolsCall($params),
            'ping'           => new \stdClass(), // empty result
            default          => $this->error($id, -32601, "Method not found: {$method}"),
        };

        // Notifications (no id) don't get a response
        if (null === $id && null === $result) {
            return '';
        }

        // Error responses pass through
        if (is_string($result) && str_contains($result, '"error"')) {
            return $result;
        }

        return $this->success($id, $result);
    }

    /**
     * Handle batch of requests (one per line for Streamable HTTP).
     */
    public function handleBatch(string $body): string
    {
        $lines    = array_filter(explode("\n", trim($body)));
        $responses = [];

        foreach ($lines as $line) {
            $response = $this->handle($line);
            if ('' !== $response) {
                $responses[] = $response;
            }
        }

        return implode("\n", $responses);
    }

    // ── Protocol Methods ───────────────────────────────────────────

    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => [
                'tools' => new \stdClass(),
            ],
            'serverInfo'      => [
                'name'    => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];
    }

    private function toolsList(): array
    {
        $agentTools = $this->agent->listTools();

        $mcpTools = [];
        foreach ($agentTools as $tool) {
            $mcpTools[] = [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ];
        }

        return ['tools' => $mcpTools];
    }

    private function toolsCall(array $params): array
    {
        $name = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        if ('' === $name) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: tool name is required']],
                'isError' => true,
            ];
        }

        $result = $this->agent->invokeToolByName($name, $args);

        // Check for errors
        if (is_array($result) && isset($result['error'])) {
            return [
                'content' => [['type' => 'text', 'text' => 'Error: ' . $result['error']]],
                'isError' => true,
            ];
        }

        // Format result as MCP content
        $text = is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'content' => [['type' => 'text', 'text' => $text]],
        ];
    }

    // ── JSON-RPC Helpers ───────────────────────────────────────────

    private function success(?string $id, mixed $result): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ], JSON_UNESCAPED_SLASHES);
    }

    private function error(?string $id, int $code, string $message): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ], JSON_UNESCAPED_SLASHES);
    }
}
