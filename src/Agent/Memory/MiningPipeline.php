<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Memory;

use ChimeraNoWP\Agent\LLM\Message;
use ChimeraNoWP\Agent\LLM\ProviderInterface;

/**
 * Mining Pipeline — extracts memories, skills, and profile updates from sessions.
 *
 * Takes a conversation session and uses AI to identify:
 * - Facts, decisions, corrections, preferences → memories
 * - Procedures, configurations, troubleshooting steps → skills
 * - User profile information → profile updates
 */
class MiningPipeline
{
    private ProviderInterface $provider;
    private MemoryService $memory;
    private int $maxSessionChars;

    private const EXTRACTION_PROMPT = <<<'PROMPT'
Analyze this conversation and extract structured information.

Return a JSON object with:
{
  "memories": [
    {"content": "what was learned", "category": "fact|decision|issue|task|correction", "tags": ["tag1"]}
  ],
  "skills": [
    {"content": "step-by-step procedure or technique", "category": "procedure|configuration|troubleshooting|workflow", "tags": ["tag1"]}
  ],
  "profile": {
    "content": "summary of what we know about the user",
    "metadata": {"role": "", "preferences": []}
  }
}

Rules:
- Only extract genuinely useful information, not trivial exchanges
- Memories are facts, decisions, corrections the user stated
- Skills are reusable procedures or techniques demonstrated in the conversation
- Profile is cumulative info about the user (role, preferences, expertise)
- If nothing useful, return {"memories": [], "skills": [], "profile": null}
- Return ONLY valid JSON, no explanation

Conversation:
PROMPT;

    public function __construct(
        ProviderInterface $provider,
        MemoryService $memory,
        int $maxSessionChars = 100000,
    ) {
        $this->provider = $provider;
        $this->memory = $memory;
        $this->maxSessionChars = $maxSessionChars;
    }

    /**
     * Mine a session for memories, skills, and profile updates.
     */
    public function mine(string $agentId, string $userId, string $sessionId): array
    {
        $session = $this->memory->getSession($agentId, $userId, $sessionId);
        if (!$session) {
            return ['error' => "Session not found: {$sessionId}"];
        }

        $content = $this->prepareContent($session);
        $prompt = self::EXTRACTION_PROMPT . "\n" . $content;

        // Call AI for extraction
        $msgObjects = [
            new Message('system', 'You extract structured information from conversations. Return only valid JSON.'),
            new Message('user', $prompt),
        ];
        $response = $this->provider->chat($msgObjects, []);

        $text = $response->content ?? '';
        $extraction = $this->parseJson($text);

        if (!$extraction) {
            return ['error' => 'Failed to parse AI extraction', 'raw' => $text];
        }

        $result = [
            'sessionId' => $sessionId,
            'memories'  => [],
            'skills'    => [],
            'profile'   => null,
        ];

        // Save extracted memories
        foreach ($extraction['memories'] ?? [] as $m) {
            $saved = $this->memory->saveMemory(
                $agentId, $userId,
                $m['content'] ?? '',
                $m['category'] ?? 'fact',
                $m['tags'] ?? [],
                $sessionId,
            );
            $result['memories'][] = $saved;
        }

        // Save extracted skills
        foreach ($extraction['skills'] ?? [] as $s) {
            $saved = $this->memory->saveSkill(
                $agentId,
                $s['content'] ?? '',
                $s['category'] ?? 'procedure',
                $s['tags'] ?? [],
            );
            $result['skills'][] = $saved;
        }

        // Update profile
        if (!empty($extraction['profile']['content'])) {
            $result['profile'] = $this->memory->saveProfile(
                $agentId, $userId,
                $extraction['profile']['content'],
                $extraction['profile']['metadata'] ?? [],
            );
        }

        // Mark session as mined
        $this->memory->markSessionMined($agentId, $userId, $sessionId);

        return $result;
    }

    /**
     * Mine all unmined sessions for an agent+user.
     */
    public function mineAll(string $agentId, string $userId): array
    {
        $sessions = $this->memory->listSessions($agentId, $userId, true);
        $results = [];

        foreach ($sessions as $session) {
            $results[] = $this->mine($agentId, $userId, $session['id']);
        }

        return [
            'sessionsProcessed' => count($results),
            'results'           => $results,
        ];
    }

    // ── Private ──────────────────────────────────────────────────

    private function prepareContent(array $session): string
    {
        $content = '';

        if (!empty($session['messages'])) {
            $lines = [];
            foreach ($session['messages'] as $m) {
                $ts = isset($m['timestamp']) ? "[{$m['timestamp']}] " : '';
                $lines[] = "{$ts}[{$m['role']}]: {$m['content']}";
            }
            $content = implode("\n", $lines);
        } else {
            $content = $session['content'] ?? '';
        }

        if (strlen($content) <= $this->maxSessionChars) {
            return $content;
        }

        // Truncate from beginning (keep most recent)
        $truncated = substr($content, strlen($content) - $this->maxSessionChars);
        $firstNewline = strpos($truncated, "\n");
        if ($firstNewline !== false && $firstNewline < 200) {
            return "[...truncated...]\n" . substr($truncated, $firstNewline + 1);
        }
        return "[...truncated...]\n" . $truncated;
    }

    private function parseJson(string $text): ?array
    {
        // Try markdown code block
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        // Try raw JSON
        if (preg_match('/(\{[\s\S]*\})/', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if ($decoded) return $decoded;
        }

        return null;
    }
}
