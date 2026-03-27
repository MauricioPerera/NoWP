<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Bridge;

use ChimeraNoWP\Agent\Core\ToolDefinition;
use ChimeraNoWP\Agent\Memory\MemoryService;

/**
 * Exposes NoWP's MemoryService (5 collections) as Chimera agent tools.
 * Replaces Chimera's original MemoryBridge which used php-agent-memory.
 */
final class MemoryBridge
{
    /**
     * @return ToolDefinition[]
     */
    public static function tools(MemoryService $memory, string $agentId = 'chimera', string $userId = 'default'): array
    {
        return [
            new ToolDefinition('recall', 'Search semantic memory for relevant past knowledge, facts, skills, and documentation. Use this before starting a task to check what you already know.', [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'What to search for in memory'],
                    'limit' => ['type' => 'integer', 'description' => 'Max items to recall (default 10)'],
                ],
                'required' => ['query'],
            ], function (array $args) use ($memory, $agentId, $userId): string {
                $result = $memory->recall(
                    $agentId,
                    $userId,
                    $args['query'],
                    maxItems: (int) ($args['limit'] ?? 10),
                );
                return json_encode([
                    'items' => array_map(fn($i) => [
                        'source' => $i['source'],
                        'score' => round($i['score'], 3),
                        'content' => $i['entity']['content'] ?? '',
                        'category' => $i['entity']['category'] ?? '',
                        'tags' => $i['entity']['tags'] ?? [],
                    ], $result['items'] ?? []),
                    'total' => $result['totalItems'] ?? 0,
                ], JSON_UNESCAPED_SLASHES);
            }, safe: true, category: 'memory'),

            new ToolDefinition('remember', 'Save a fact, decision, issue, task, or correction to persistent memory. Will deduplicate automatically.', [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'What to remember'],
                    'category' => ['type' => 'string', 'description' => 'Category: fact, decision, issue, task, correction'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tags for organization'],
                ],
                'required' => ['content'],
            ], function (array $args) use ($memory, $agentId, $userId): string {
                $result = $memory->saveMemory(
                    $agentId,
                    $userId,
                    $args['content'],
                    $args['category'] ?? 'fact',
                    $args['tags'] ?? [],
                );
                return json_encode($result, JSON_UNESCAPED_SLASHES);
            }, category: 'memory'),

            new ToolDefinition('learn_skill', 'Save a procedure, configuration, troubleshooting step, or workflow to skills memory.', [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Skill content to save'],
                    'category' => ['type' => 'string', 'description' => 'Category: procedure, configuration, troubleshooting, workflow'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tags'],
                ],
                'required' => ['content'],
            ], function (array $args) use ($memory, $agentId): string {
                $result = $memory->saveSkill(
                    $agentId,
                    $args['content'],
                    $args['category'] ?? 'procedure',
                    $args['tags'] ?? [],
                );
                return json_encode($result, JSON_UNESCAPED_SLASHES);
            }, category: 'memory'),

            new ToolDefinition('save_knowledge', 'Save documentation, reference content, or chunked knowledge for future recall.', [
                'type' => 'object',
                'properties' => [
                    'content' => ['type' => 'string', 'description' => 'Knowledge content'],
                    'source' => ['type' => 'string', 'description' => 'Source identifier'],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tags'],
                ],
                'required' => ['content'],
            ], function (array $args) use ($memory, $agentId): string {
                $result = $memory->saveKnowledge(
                    $agentId,
                    $args['content'],
                    $args['tags'] ?? [],
                    $args['source'] ?? null,
                );
                return json_encode($result, JSON_UNESCAPED_SLASHES);
            }, category: 'memory'),

            new ToolDefinition('memory_stats', 'Get statistics about stored memories, skills, knowledge, and sessions.', [
                'type' => 'object', 'properties' => (object)[], 'required' => [],
            ], function (array $args) use ($memory, $agentId, $userId): string {
                return json_encode($memory->stats($agentId, $userId), JSON_UNESCAPED_SLASHES);
            }, safe: true, category: 'memory'),
        ];
    }
}
