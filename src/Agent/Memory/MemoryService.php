<?php

declare(strict_types=1);

namespace Framework\Agent\Memory;

use Framework\Search\SearchService;

/**
 * Agent Memory — persistent semantic memory across sessions.
 *
 * Stores memories as text with vector embeddings for semantic recall.
 * Uses php-vector-store via SearchService for storage and retrieval.
 */
class MemoryService
{
    private SearchService $search;
    private string $storagePath;

    public function __construct(SearchService $search, string $storagePath)
    {
        $this->search      = $search;
        $this->storagePath = rtrim($storagePath, '/');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Save a memory.
     *
     * @param string $agentId  Agent identifier.
     * @param string $content  Memory content.
     * @param string $type     Memory type: fact, preference, correction, event, task.
     * @param array  $tags     Optional tags for organization.
     * @return string Memory ID.
     */
    public function save(string $agentId, string $content, string $type = 'fact', array $tags = []): string
    {
        $id   = uniqid('mem_', true);
        $data = [
            'id'        => $id,
            'agent_id'  => $agentId,
            'content'   => $content,
            'type'      => $type,
            'tags'      => $tags,
            'created_at' => date('c'),
        ];

        // Save to file
        $this->writeMemory($agentId, $id, $data);

        // Index for semantic search
        $collection = "memory_{$agentId}";
        $this->search->index($collection, $id, $content, [
            'type' => $type,
            'tags' => implode(',', $tags),
        ]);

        return $id;
    }

    /**
     * Recall memories by semantic similarity.
     *
     * @param string $agentId Agent identifier.
     * @param string $query   What to remember.
     * @param int    $limit   Max memories.
     * @return array Matched memories with scores.
     */
    public function recall(string $agentId, string $query, int $limit = 5): array
    {
        $collection = "memory_{$agentId}";
        $results    = $this->search->search($collection, $query, $limit);

        $memories = [];
        foreach ($results as $r) {
            $data = $this->readMemory($agentId, $r['id']);
            if ($data) {
                $data['score'] = $r['score'];
                $memories[]    = $data;
            }
        }

        return $memories;
    }

    /**
     * Get all memories for an agent.
     */
    public function list(string $agentId, ?string $type = null): array
    {
        $dir = $this->agentDir($agentId);
        if (!is_dir($dir)) return [];

        $memories = [];
        foreach (glob($dir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;
            if ($type && ($data['type'] ?? '') !== $type) continue;
            $memories[] = $data;
        }

        usort($memories, fn($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));
        return $memories;
    }

    /**
     * Delete a memory.
     */
    public function delete(string $agentId, string $memoryId): bool
    {
        $file = $this->agentDir($agentId) . '/' . $memoryId . '.json';
        if (!file_exists($file)) return false;

        unlink($file);
        $this->search->remove("memory_{$agentId}", $memoryId);
        return true;
    }

    /**
     * Count memories for an agent.
     */
    public function count(string $agentId): int
    {
        $dir = $this->agentDir($agentId);
        if (!is_dir($dir)) return 0;
        return count(glob($dir . '/*.json'));
    }

    // ── Private ──────────────────────────────────────────────────────

    private function writeMemory(string $agentId, string $id, array $data): void
    {
        $dir = $this->agentDir($agentId);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $id . '.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    private function readMemory(string $agentId, string $id): ?array
    {
        $file = $this->agentDir($agentId) . '/' . $id . '.json';
        if (!file_exists($file)) return null;
        return json_decode(file_get_contents($file), true);
    }

    private function agentDir(string $agentId): string
    {
        return $this->storagePath . '/' . preg_replace('/[^a-z0-9_-]/', '_', strtolower($agentId));
    }
}
