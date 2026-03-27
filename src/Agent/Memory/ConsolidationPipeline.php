<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Memory;

use ChimeraNoWP\Agent\LLM\Message;
use ChimeraNoWP\Agent\LLM\ProviderInterface;

/**
 * Consolidation Pipeline — merges duplicate/related memories and skills.
 *
 * Uses AI to identify groups of similar items that should be merged,
 * obsolete items that should be removed, and items to keep as-is.
 */
class ConsolidationPipeline
{
    private ProviderInterface $provider;
    private MemoryService $memory;

    private const CHUNK_SIZE = 20;

    private const CONSOLIDATION_PROMPT = <<<'PROMPT'
Review these items and identify duplicates, overlaps, and obsolete entries.

Return a JSON object with:
{
  "merge": [
    {
      "sourceIds": ["id1", "id2"],
      "merged": {"content": "combined content", "tags": ["tag1"], "category": "fact"}
    }
  ],
  "remove": ["id_of_obsolete_item"],
  "keep": ["id_of_item_to_keep"]
}

Rules:
- Merge items that say the same thing differently
- Remove items that are clearly outdated or superseded
- Keep items that are unique and valuable
- ONLY use IDs from the provided list
- The merged content should be the best version combining all sources
- Return ONLY valid JSON

Items:
PROMPT;

    public function __construct(ProviderInterface $provider, MemoryService $memory)
    {
        $this->provider = $provider;
        $this->memory = $memory;
    }

    /**
     * Consolidate memories for an agent+user.
     */
    public function consolidateMemories(string $agentId, string $userId): array
    {
        $memories = $this->memory->listMemories($agentId, $userId);
        if (count($memories) < 2) {
            return ['merged' => 0, 'removed' => 0, 'kept' => count($memories)];
        }

        return $this->processChunked($memories, 'memories', $agentId, $userId);
    }

    /**
     * Consolidate skills for an agent.
     */
    public function consolidateSkills(string $agentId): array
    {
        $skills = $this->memory->listSkills($agentId);
        if (count($skills) < 2) {
            return ['merged' => 0, 'removed' => 0, 'kept' => count($skills)];
        }

        return $this->processChunked($skills, 'skills', $agentId, '_shared');
    }

    /**
     * Consolidate knowledge for an agent.
     */
    public function consolidateKnowledge(string $agentId): array
    {
        $knowledge = $this->memory->listKnowledge($agentId);
        if (count($knowledge) < 2) {
            return ['merged' => 0, 'removed' => 0, 'kept' => count($knowledge)];
        }

        return $this->processChunked($knowledge, 'knowledge', $agentId, '_shared');
    }

    /**
     * Run full consolidation (all collections).
     */
    public function runAll(string $agentId, string $userId): array
    {
        return [
            'memories'  => $this->consolidateMemories($agentId, $userId),
            'skills'    => $this->consolidateSkills($agentId),
            'knowledge' => $this->consolidateKnowledge($agentId),
        ];
    }

    // ── Private ──────────────────────────────────────────────────

    private function processChunked(array $items, string $collection, string $agentId, string $scopeId): array
    {
        // Group by category (if present)
        $groups = [];
        foreach ($items as $item) {
            $cat = $item['category'] ?? 'all';
            $groups[$cat][] = $item;
        }

        $totalMerged = 0;
        $totalRemoved = 0;
        $totalKept = 0;

        foreach ($groups as $group) {
            for ($i = 0; $i < count($group); $i += self::CHUNK_SIZE) {
                $chunk = array_slice($group, $i, self::CHUNK_SIZE);
                $result = $this->processChunk($chunk, $collection, $agentId, $scopeId);
                $totalMerged += $result['merged'];
                $totalRemoved += $result['removed'];
                $totalKept += $result['kept'];
            }
        }

        return ['merged' => $totalMerged, 'removed' => $totalRemoved, 'kept' => $totalKept];
    }

    private function processChunk(array $items, string $collection, string $agentId, string $scopeId): array
    {
        if (count($items) < 2) {
            return ['merged' => 0, 'removed' => 0, 'kept' => count($items)];
        }

        // Serialize for AI
        $serialized = json_encode(
            array_map(fn($i) => [
                'id'       => $i['id'],
                'content'  => $i['content'],
                'tags'     => $i['tags'] ?? [],
                'category' => $i['category'] ?? null,
            ], $items),
            JSON_PRETTY_PRINT
        );

        $prompt = self::CONSOLIDATION_PROMPT . "\n" . $serialized;

        $msgObjects = [
            new Message('system', 'You consolidate items by merging duplicates and removing obsolete entries. Return only valid JSON.'),
            new Message('user', $prompt),
        ];
        $response = $this->provider->chat($msgObjects, []);

        $plan = $this->parseJson($response->content ?? '');
        if (!$plan) {
            return ['merged' => 0, 'removed' => 0, 'kept' => count($items)];
        }

        // Validate IDs against chunk
        $validIds = array_flip(array_column($items, 'id'));

        $merged = 0;
        $removed = 0;

        // Process merges
        foreach ($plan['merge'] ?? [] as $merge) {
            $sourceIds = array_filter($merge['sourceIds'] ?? [], fn($id) => isset($validIds[$id]));
            if (count($sourceIds) < 2) continue;

            $mergedContent = $merge['merged']['content'] ?? '';
            if (empty(trim($mergedContent))) continue;

            // Save merged version
            match ($collection) {
                'memories' => $this->memory->saveMemory(
                    $agentId, $scopeId, $mergedContent,
                    $merge['merged']['category'] ?? 'fact',
                    $merge['merged']['tags'] ?? [],
                ),
                'skills' => $this->memory->saveSkill(
                    $agentId, $mergedContent,
                    $merge['merged']['category'] ?? 'procedure',
                    $merge['merged']['tags'] ?? [],
                ),
                'knowledge' => $this->memory->saveKnowledge(
                    $agentId, $mergedContent,
                    $merge['merged']['tags'] ?? [],
                ),
                default => null,
            };

            // Delete source items
            foreach ($sourceIds as $id) {
                $this->memory->delete($collection, $agentId, $scopeId, $id);
            }

            $merged += count($sourceIds);
        }

        // Process removals
        foreach ($plan['remove'] ?? [] as $removeId) {
            if (!isset($validIds[$removeId])) continue;
            $this->memory->delete($collection, $agentId, $scopeId, $removeId);
            $removed++;
        }

        $kept = count($plan['keep'] ?? []);

        return ['merged' => $merged, 'removed' => $removed, 'kept' => $kept];
    }

    private function parseJson(string $text): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        if (preg_match('/(\{[\s\S]*\})/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        return null;
    }
}
