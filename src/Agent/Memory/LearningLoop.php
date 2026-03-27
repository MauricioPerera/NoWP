<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Memory;

use ChimeraNoWP\Agent\LLM\Message;
use ChimeraNoWP\Agent\LLM\ProviderInterface;

/**
 * Post-conversation self-improvement: extract knowledge and save to MemoryService.
 * Adapted from Chimera's LearningLoop to use NoWP's MemoryService.
 */
final class LearningLoop
{
    public function __construct(
        private readonly ProviderInterface $llm,
        private readonly ?MemoryService $memoryService = null,
        private readonly string $agentId = 'chimera',
        private readonly string $userId = 'default',
    ) {}

    /**
     * @param Message[] $messages Conversation history
     * @return array{sessionSaved: bool, memoriesExtracted: int, skillsExtracted: int}
     */
    public function learn(array $messages, bool $usedTools): array
    {
        $result = ['sessionSaved' => false, 'memoriesExtracted' => 0, 'skillsExtracted' => 0];

        if (!$this->memoryService || !$usedTools) {
            return $result;
        }

        // Build conversation transcript
        $transcript = '';
        foreach ($messages as $msg) {
            if ($msg->role === 'system') continue;
            $content = $msg->content ?? '';
            if ($content !== '') $transcript .= "[{$msg->role}]: {$content}\n";
        }

        if (strlen($transcript) < 100) return $result;

        $extractionPrompt = <<<PROMPT
Analyze this conversation and extract useful knowledge. Return JSON only:
{
  "memories": [{"content": "...", "tags": ["..."], "category": "fact|decision|issue|task|correction"}],
  "skills": [{"content": "...", "tags": ["..."], "category": "procedure|configuration|troubleshooting|workflow"}]
}
Rules:
- Max 3 memories, max 2 skills
- Only extract genuinely useful, reusable information
- Skip trivial or conversation-specific details
- If nothing worth saving, return empty arrays

Conversation:
{$transcript}
PROMPT;

        try {
            $response = $this->llm->chat([
                Message::system('You extract knowledge from conversations. Return valid JSON only.'),
                Message::user($extractionPrompt),
            ]);

            $json = $response->content ?? '';
            if (preg_match('/```(?:json)?\s*(.+?)```/s', $json, $m)) $json = $m[1];
            $data = json_decode(trim($json), true);

            if (!is_array($data)) return $result;

            // Save memories via MemoryService
            foreach (($data['memories'] ?? []) as $mem) {
                if (empty($mem['content'])) continue;
                $this->memoryService->saveMemory(
                    $this->agentId,
                    $this->userId,
                    $mem['content'],
                    $mem['category'] ?? 'fact',
                    $mem['tags'] ?? [],
                );
                $result['memoriesExtracted']++;
            }

            // Save skills via MemoryService
            foreach (($data['skills'] ?? []) as $skill) {
                if (empty($skill['content'])) continue;
                $this->memoryService->saveSkill(
                    $this->agentId,
                    $skill['content'],
                    $skill['category'] ?? 'procedure',
                    $skill['tags'] ?? [],
                );
                $result['skillsExtracted']++;
            }

            if ($result['memoriesExtracted'] > 0 || $result['skillsExtracted'] > 0) {
                $result['sessionSaved'] = true;
            }
        } catch (\Throwable) {
            // Learning is best-effort
        }

        return $result;
    }
}
