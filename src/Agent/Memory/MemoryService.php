<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Memory;

use ChimeraNoWP\Search\SearchService;

/**
 * Agent Memory — persistent semantic memory across sessions.
 *
 * Implements the RepoMemory philosophy: 5 collection types with
 * mining, consolidation, recall, and access tracking.
 *
 * Collections:
 *   memories   — facts, decisions, issues, tasks, corrections
 *   skills     — procedures, configurations, troubleshooting, workflows
 *   knowledge  — documentation, reference, chunked content
 *   sessions   — conversation logs (mineable)
 *   profiles   — user/agent profiles (one per user per agent)
 */
class MemoryService
{
    private SearchService $search;
    private string $storagePath;

    /** @var array<string, int> access counts (in-memory, flushed periodically) */
    private array $accessCounts = [];
    private bool $accessDirty = false;

    public function __construct(SearchService $search, string $storagePath)
    {
        $this->search      = $search;
        $this->storagePath = rtrim($storagePath, '/');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $this->loadAccessCounts();
    }

    // ── Memories ─────────────────────────────────────────────────

    /**
     * Save or update a memory. Deduplicates by semantic similarity.
     */
    public function saveMemory(
        string $agentId,
        string $userId,
        string $content,
        string $category = 'fact',
        array $tags = [],
        ?string $sourceSession = null,
    ): array {
        // Check for semantic duplicates
        $existing = $this->search->search("memories_{$agentId}_{$userId}", $content, 1);
        if (!empty($existing) && ($existing[0]['score'] ?? 0) > 0.92) {
            // Update existing instead of creating duplicate
            $id = $existing[0]['id'];
            $data = $this->read('memories', $agentId, $userId, $id);
            if ($data) {
                $data['content'] = $content;
                $data['tags'] = array_unique(array_merge($data['tags'] ?? [], $tags));
                $data['category'] = $category;
                $data['updatedAt'] = date('c');
                $data['accessCount'] = ($data['accessCount'] ?? 0);
                $this->write('memories', $agentId, $userId, $id, $data);
                $this->search->index("memories_{$agentId}_{$userId}", $id, $content, ['category' => $category]);
                return ['id' => $id, 'action' => 'updated', 'data' => $data];
            }
        }

        $id = $this->generateId('mem');
        $data = [
            'id'            => $id,
            'type'          => 'memory',
            'agentId'       => $agentId,
            'userId'        => $userId,
            'content'       => $content,
            'category'      => $category,
            'tags'          => $tags,
            'sourceSession' => $sourceSession,
            'accessCount'   => 0,
            'createdAt'     => date('c'),
            'updatedAt'     => date('c'),
        ];

        $this->write('memories', $agentId, $userId, $id, $data);
        $this->search->index("memories_{$agentId}_{$userId}", $id, $content, ['category' => $category]);

        return ['id' => $id, 'action' => 'created', 'data' => $data];
    }

    /**
     * List memories for an agent+user.
     */
    public function listMemories(string $agentId, string $userId, ?string $category = null): array
    {
        return $this->listCollection('memories', $agentId, $userId, $category ? ['category' => $category] : []);
    }

    // ── Skills ───────────────────────────────────────────────────

    /**
     * Save or update a skill. Skills are agent-scoped (not user-specific).
     */
    public function saveSkill(
        string $agentId,
        string $content,
        string $category = 'procedure',
        array $tags = [],
        string $status = 'active',
    ): array {
        // Dedup
        $existing = $this->search->search("skills_{$agentId}", $content, 1);
        if (!empty($existing) && ($existing[0]['score'] ?? 0) > 0.92) {
            $id = $existing[0]['id'];
            $data = $this->read('skills', $agentId, '_shared', $id);
            if ($data) {
                $data['content'] = $content;
                $data['tags'] = array_unique(array_merge($data['tags'] ?? [], $tags));
                $data['category'] = $category;
                $data['status'] = $status;
                $data['updatedAt'] = date('c');
                $this->write('skills', $agentId, '_shared', $id, $data);
                $this->search->index("skills_{$agentId}", $id, $content, ['category' => $category]);
                return ['id' => $id, 'action' => 'updated', 'data' => $data];
            }
        }

        $id = $this->generateId('skl');
        $data = [
            'id'          => $id,
            'type'        => 'skill',
            'agentId'     => $agentId,
            'content'     => $content,
            'category'    => $category,
            'tags'        => $tags,
            'status'      => $status,
            'accessCount' => 0,
            'createdAt'   => date('c'),
            'updatedAt'   => date('c'),
        ];

        $this->write('skills', $agentId, '_shared', $id, $data);
        $this->search->index("skills_{$agentId}", $id, $content, ['category' => $category]);

        return ['id' => $id, 'action' => 'created', 'data' => $data];
    }

    public function listSkills(string $agentId, ?string $category = null): array
    {
        return $this->listCollection('skills', $agentId, '_shared', $category ? ['category' => $category] : []);
    }

    // ── Knowledge ────────────────────────────────────────────────

    /**
     * Save knowledge (documentation, reference content).
     */
    public function saveKnowledge(
        string $agentId,
        string $content,
        array $tags = [],
        ?string $source = null,
        ?int $chunkIndex = null,
        ?string $version = null,
        array $questions = [],
    ): array {
        // Dedup
        $existing = $this->search->search("knowledge_{$agentId}", $content, 1);
        if (!empty($existing) && ($existing[0]['score'] ?? 0) > 0.95) {
            $id = $existing[0]['id'];
            $data = $this->read('knowledge', $agentId, '_shared', $id);
            if ($data) {
                $data['content'] = $content;
                $data['tags'] = array_unique(array_merge($data['tags'] ?? [], $tags));
                $data['version'] = $version ?? $data['version'];
                $data['updatedAt'] = date('c');
                $this->write('knowledge', $agentId, '_shared', $id, $data);
                $this->search->index("knowledge_{$agentId}", $id, $content);
                return ['id' => $id, 'action' => 'updated', 'data' => $data];
            }
        }

        $id = $this->generateId('kno');
        $data = [
            'id'          => $id,
            'type'        => 'knowledge',
            'agentId'     => $agentId,
            'content'     => $content,
            'tags'        => $tags,
            'source'      => $source,
            'chunkIndex'  => $chunkIndex,
            'version'     => $version,
            'questions'   => $questions,
            'accessCount' => 0,
            'createdAt'   => date('c'),
            'updatedAt'   => date('c'),
        ];

        $this->write('knowledge', $agentId, '_shared', $id, $data);
        $this->search->index("knowledge_{$agentId}", $id, $content);

        return ['id' => $id, 'action' => 'created', 'data' => $data];
    }

    public function listKnowledge(string $agentId): array
    {
        return $this->listCollection('knowledge', $agentId, '_shared');
    }

    // ── Sessions ─────────────────────────────────────────────────

    /**
     * Save a conversation session for later mining.
     */
    public function saveSession(
        string $agentId,
        string $userId,
        string $content,
        array $messages = [],
        ?string $conversationId = null,
    ): array {
        $id = $this->generateId('ses');
        $data = [
            'id'             => $id,
            'type'           => 'session',
            'agentId'        => $agentId,
            'userId'         => $userId,
            'content'        => $content,
            'messages'       => $messages,
            'mined'          => false,
            'conversationId' => $conversationId,
            'startedAt'      => date('c'),
            'endedAt'        => null,
            'createdAt'      => date('c'),
            'updatedAt'      => date('c'),
        ];

        $this->write('sessions', $agentId, $userId, $id, $data);

        return ['id' => $id, 'action' => 'created', 'data' => $data];
    }

    /**
     * End a session.
     */
    public function endSession(string $agentId, string $userId, string $sessionId): array
    {
        $data = $this->read('sessions', $agentId, $userId, $sessionId);
        if (!$data) return ['error' => 'Session not found'];

        $data['endedAt'] = date('c');
        $data['updatedAt'] = date('c');
        $this->write('sessions', $agentId, $userId, $sessionId, $data);

        return ['id' => $sessionId, 'action' => 'ended'];
    }

    /**
     * Mark a session as mined.
     */
    public function markSessionMined(string $agentId, string $userId, string $sessionId): void
    {
        $data = $this->read('sessions', $agentId, $userId, $sessionId);
        if ($data) {
            $data['mined'] = true;
            $data['updatedAt'] = date('c');
            $this->write('sessions', $agentId, $userId, $sessionId, $data);
        }
    }

    public function listSessions(string $agentId, string $userId, bool $onlyUnmined = false): array
    {
        $all = $this->listCollection('sessions', $agentId, $userId);
        if ($onlyUnmined) {
            return array_values(array_filter($all, fn($s) => !($s['mined'] ?? false)));
        }
        return $all;
    }

    public function getSession(string $agentId, string $userId, string $sessionId): ?array
    {
        return $this->read('sessions', $agentId, $userId, $sessionId);
    }

    // ── Profiles ─────────────────────────────────────────────────

    /**
     * Save or update a user profile (one per user per agent).
     */
    public function saveProfile(string $agentId, string $userId, string $content, array $metadata = []): array
    {
        $existing = $this->getProfile($agentId, $userId);

        if ($existing) {
            $existing['content'] = $content;
            $existing['metadata'] = array_merge($existing['metadata'] ?? [], $metadata);
            $existing['updatedAt'] = date('c');
            $this->write('profiles', $agentId, $userId, $existing['id'], $existing);
            return ['id' => $existing['id'], 'action' => 'updated', 'data' => $existing];
        }

        $id = $this->generateId('prf');
        $data = [
            'id'        => $id,
            'type'      => 'profile',
            'agentId'   => $agentId,
            'userId'    => $userId,
            'content'   => $content,
            'metadata'  => $metadata,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ];

        $this->write('profiles', $agentId, $userId, $id, $data);

        return ['id' => $id, 'action' => 'created', 'data' => $data];
    }

    public function getProfile(string $agentId, string $userId): ?array
    {
        $all = $this->listCollection('profiles', $agentId, $userId);
        return $all[0] ?? null;
    }

    // ── Recall Engine ────────────────────────────────────────────

    /**
     * Unified recall across all collections.
     * Returns ranked results from memories, skills, and knowledge.
     */
    public function recall(
        string $agentId,
        string $userId,
        string $query,
        int $maxItems = 20,
        int $maxChars = 8000,
        array $collections = ['memories', 'skills', 'knowledge'],
        array $weights = [],
    ): array {
        $memWeight = $weights['memories'] ?? 1.0;
        $sklWeight = $weights['skills'] ?? 1.0;
        $knoWeight = $weights['knowledge'] ?? 1.0;

        $fetchLimit = max(10, $maxItems * 2);
        $pool = [];

        if (in_array('memories', $collections)) {
            foreach ($this->search->search("memories_{$agentId}_{$userId}", $query, $fetchLimit) as $r) {
                $data = $this->read('memories', $agentId, $userId, $r['id']);
                if ($data) {
                    $pool[] = ['source' => 'memories', 'score' => ($r['score'] ?? 0) * $memWeight, 'entity' => $data];
                }
            }
        }

        if (in_array('skills', $collections)) {
            foreach ($this->search->search("skills_{$agentId}", $query, $fetchLimit) as $r) {
                $data = $this->read('skills', $agentId, '_shared', $r['id']);
                if ($data) {
                    $pool[] = ['source' => 'skills', 'score' => ($r['score'] ?? 0) * $sklWeight, 'entity' => $data];
                }
            }
        }

        if (in_array('knowledge', $collections)) {
            foreach ($this->search->search("knowledge_{$agentId}", $query, $fetchLimit) as $r) {
                $data = $this->read('knowledge', $agentId, '_shared', $r['id']);
                if ($data) {
                    $pool[] = ['source' => 'knowledge', 'score' => ($r['score'] ?? 0) * $knoWeight, 'entity' => $data];
                }
            }
        }

        // Sort by score, take top N
        usort($pool, fn($a, $b) => $b['score'] <=> $a['score']);
        $selected = array_slice($pool, 0, $maxItems);

        // Track access
        $ids = array_map(fn($r) => $r['entity']['id'], $selected);
        $this->trackAccess($ids);

        // Get profile
        $profile = $this->getProfile($agentId, $userId);

        // Format for prompt injection
        $formatted = $this->formatRecall($selected, $profile, $maxChars);

        return [
            'items'         => $selected,
            'profile'       => $profile,
            'formatted'     => $formatted,
            'totalItems'    => count($selected),
            'estimatedChars' => strlen($formatted),
        ];
    }

    /**
     * Simple recall (backwards compatible) — returns just memory content+score.
     */
    public function simpleRecall(string $agentId, string $query, int $limit = 5): array
    {
        // Search in the default user scope
        $results = $this->search->search("memories_{$agentId}_default", $query, $limit);
        $memories = [];

        foreach ($results as $r) {
            $data = $this->read('memories', $agentId, 'default', $r['id']);
            if ($data) {
                $data['score'] = $r['score'];
                $memories[] = $data;
            }
        }

        return $memories;
    }

    // ── Stats ────────────────────────────────────────────────────

    public function stats(string $agentId, string $userId = 'default'): array
    {
        return [
            'memories'  => count($this->listMemories($agentId, $userId)),
            'skills'    => count($this->listSkills($agentId)),
            'knowledge' => count($this->listKnowledge($agentId)),
            'sessions'  => count($this->listSessions($agentId, $userId)),
            'profile'   => $this->getProfile($agentId, $userId) !== null,
        ];
    }

    // ── Delete ───────────────────────────────────────────────────

    public function delete(string $collection, string $agentId, string $scopeId, string $id): bool
    {
        $file = $this->filePath($collection, $agentId, $scopeId, $id);
        if (!file_exists($file)) return false;

        unlink($file);

        $searchCollection = match ($collection) {
            'memories' => "memories_{$agentId}_{$scopeId}",
            'skills'   => "skills_{$agentId}",
            'knowledge' => "knowledge_{$agentId}",
            default    => null,
        };
        if ($searchCollection) {
            $this->search->remove($searchCollection, $id);
        }

        return true;
    }

    // ── Access Tracking ──────────────────────────────────────────

    private function trackAccess(array $ids): void
    {
        foreach ($ids as $id) {
            $this->accessCounts[$id] = ($this->accessCounts[$id] ?? 0) + 1;
            $this->accessDirty = true;
        }

        // Flush every 50 accesses
        if ($this->accessDirty && array_sum($this->accessCounts) % 50 === 0) {
            $this->flushAccessCounts();
        }
    }

    // ── Private Helpers ──────────────────────────────────────────

    private function write(string $collection, string $agentId, string $scopeId, string $id, array $data): void
    {
        $dir = $this->dirPath($collection, $agentId, $scopeId);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $id . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function read(string $collection, string $agentId, string $scopeId, string $id): ?array
    {
        $file = $this->filePath($collection, $agentId, $scopeId, $id);
        if (!file_exists($file)) return null;
        return json_decode(file_get_contents($file), true);
    }

    private function listCollection(string $collection, string $agentId, string $scopeId, array $filters = []): array
    {
        $dir = $this->dirPath($collection, $agentId, $scopeId);
        if (!is_dir($dir)) return [];

        $items = [];
        foreach (glob($dir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;

            $match = true;
            foreach ($filters as $key => $value) {
                if (($data[$key] ?? null) !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) $items[] = $data;
        }

        usort($items, fn($a, $b) => ($b['createdAt'] ?? '') <=> ($a['createdAt'] ?? ''));
        return $items;
    }

    private function dirPath(string $collection, string $agentId, string $scopeId): string
    {
        $agent = preg_replace('/[^a-z0-9_-]/', '_', strtolower($agentId));
        $scope = preg_replace('/[^a-z0-9_-]/', '_', strtolower($scopeId));
        return "{$this->storagePath}/{$collection}/{$agent}/{$scope}";
    }

    private function filePath(string $collection, string $agentId, string $scopeId, string $id): string
    {
        return $this->dirPath($collection, $agentId, $scopeId) . '/' . $id . '.json';
    }

    private function generateId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    private function formatRecall(array $items, ?array $profile, int $maxChars): string
    {
        $parts = [];

        if ($profile) {
            $parts[] = "## User Profile\n{$profile['content']}";
        }

        $bySource = ['memories' => [], 'skills' => [], 'knowledge' => []];
        foreach ($items as $item) {
            $bySource[$item['source']][] = $item;
        }

        if (!empty($bySource['memories'])) {
            $parts[] = "## Relevant Memories";
            foreach ($bySource['memories'] as $m) {
                $tags = !empty($m['entity']['tags']) ? ' [' . implode(', ', $m['entity']['tags']) . ']' : '';
                $parts[] = "- [{$m['entity']['category']}]{$tags} {$m['entity']['content']}";
            }
        }

        if (!empty($bySource['skills'])) {
            $parts[] = "\n## Relevant Skills";
            foreach ($bySource['skills'] as $s) {
                $parts[] = "- [{$s['entity']['category']}] {$s['entity']['content']}";
            }
        }

        if (!empty($bySource['knowledge'])) {
            $parts[] = "\n## Relevant Knowledge";
            foreach ($bySource['knowledge'] as $k) {
                $src = $k['entity']['source'] ? " (source: {$k['entity']['source']})" : '';
                $parts[] = "- {$k['entity']['content']}{$src}";
            }
        }

        $text = implode("\n", $parts);

        // Truncate if too long
        if (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars - 20) . "\n[...truncated...]";
        }

        return $text;
    }

    private function loadAccessCounts(): void
    {
        $file = $this->storagePath . '/access_counts.json';
        if (file_exists($file)) {
            $this->accessCounts = json_decode(file_get_contents($file), true) ?: [];
        }
    }

    private function flushAccessCounts(): void
    {
        if (!$this->accessDirty) return;
        file_put_contents(
            $this->storagePath . '/access_counts.json',
            json_encode($this->accessCounts, JSON_PRETTY_PRINT)
        );
        $this->accessDirty = false;
    }

    public function __destruct()
    {
        $this->flushAccessCounts();
    }

    // ── Backwards Compatibility ──────────────────────────────────

    /**
     * Simple save (backwards compatible).
     */
    public function save(string $agentId, string $content, string $type = 'fact', array $tags = []): string
    {
        $result = $this->saveMemory($agentId, 'default', $content, $type, $tags);
        return $result['id'];
    }
}
