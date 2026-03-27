<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Bridge;

use ChimeraNoWP\Agent\Core\ToolDefinition;
use ChimeraNoWP\Agent\Workflow\WorkflowEngine;

/**
 * Exposes NoWP's WorkflowEngine as Chimera agent tools.
 */
final class WorkflowBridge
{
    /**
     * @return ToolDefinition[]
     */
    public static function tools(WorkflowEngine $engine): array
    {
        return [
            new ToolDefinition(
                name: 'run_workflow',
                description: 'Execute a multi-step A2E workflow. Steps can include ExecuteTool, FilterData, TransformData, Conditional, Loop, StoreData, Wait, MergeData.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'steps' => [
                            'type' => 'array',
                            'description' => 'Array of workflow step objects with id, operation, and params',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string', 'description' => 'Step identifier'],
                                    'operation' => ['type' => 'string', 'description' => 'Operation type'],
                                    'params' => ['type' => 'object', 'description' => 'Operation parameters'],
                                ],
                            ],
                        ],
                        'initial_data' => [
                            'type' => 'object',
                            'description' => 'Initial data to pass to the workflow (optional)',
                        ],
                    ],
                    'required' => ['steps'],
                ],
                handler: function (array $args) use ($engine): string {
                    try {
                        $steps = $args['steps'] ?? [];
                        $initial = $args['initial_data'] ?? null;
                        $result = $engine->run($steps, $initial);
                        return json_encode($result, JSON_UNESCAPED_SLASHES);
                    } catch (\Throwable $e) {
                        return json_encode(['error' => $e->getMessage()]);
                    }
                },
                safe: false,
                category: 'workflow',
            ),
        ];
    }
}
