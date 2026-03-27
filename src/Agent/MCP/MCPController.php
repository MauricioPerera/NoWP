<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\MCP;

use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;

/**
 * MCP HTTP Controller.
 *
 * Single endpoint that handles all MCP JSON-RPC communication:
 *   POST /api/mcp — JSON-RPC 2.0 requests
 *   GET  /api/mcp — SSE stream (for Streamable HTTP transport)
 */
class MCPController
{
    public function __construct(
        private MCPServer $server,
    ) {}

    /**
     * POST /api/mcp
     * Handles JSON-RPC requests. Supports single and batch.
     */
    public function handle(Request $request): Response
    {
        $body     = $request->rawBody();
        $response = $this->server->handle($body);

        if ('' === $response) {
            return new Response('', 202, ['Content-Type' => 'application/json']);
        }

        return new Response($response, 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}
