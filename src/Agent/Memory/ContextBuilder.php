<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Memory;

use ChimeraNoWP\Agent\LLM\Message;

/**
 * Builds context-augmented system prompts with memory recall and history trimming.
 * Adapted from Chimera's ContextBuilder to use NoWP's MemoryService.
 */
final class ContextBuilder
{
    public function __construct(
        private readonly string $basePrompt = '',
        private readonly int $maxChars = 50000,
        private readonly int $maxMessages = 40,
        private readonly ?MemoryService $memoryService = null,
        private readonly string $agentId = 'chimera',
        private readonly string $userId = 'default',
    ) {}

    /**
     * Build messages array with memory-augmented system prompt.
     *
     * @param Message[] $history Existing conversation history
     * @param string $userMessage The new user message
     * @return Message[]
     */
    public function build(array $history, string $userMessage): array
    {
        $systemPrompt = $this->basePrompt ?: $this->defaultPrompt();

        // Inject memory recall if MemoryService available
        if ($this->memoryService) {
            $recall = $this->recall($userMessage);
            if ($recall !== '') {
                $systemPrompt .= "\n\n<MEMORY>\n{$recall}\n</MEMORY>";
            }
        }

        // Build messages: system + trimmed history + new user message
        $messages = [Message::system($systemPrompt)];

        $trimmed = $this->trimHistory($history);
        foreach ($trimmed as $msg) {
            $messages[] = $msg;
        }

        $messages[] = Message::user($userMessage);
        return $messages;
    }

    private function recall(string $query): string
    {
        try {
            $result = $this->memoryService->recall(
                $this->agentId,
                $this->userId,
                $query,
                maxItems: 10,
                maxChars: 6000,
            );
            return $result['formatted'] ?? '';
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return Message[] */
    private function trimHistory(array $history): array
    {
        if (count($history) <= $this->maxMessages) return $history;

        // Keep first user message + last N messages
        $first = null;
        foreach ($history as $msg) {
            if ($msg->role === 'user') { $first = $msg; break; }
        }

        $tail = array_slice($history, -($this->maxMessages - 1));
        return $first ? array_merge([$first], $tail) : $tail;
    }

    private function defaultPrompt(): string
    {
        return <<<'PROMPT'
You are Chimera NoWP, an autonomous AI agent powering an agentic CMS built in PHP.

You have access to tools for:
- Content management (search, create, update, list content)
- Media management (upload, list files)
- Semantic memory (recall past knowledge, remember new facts, learn skills)
- Workflow execution (A2E automation)
- Shell commands (when available)

Guidelines:
- Use tools to accomplish tasks. Search before creating.
- Save important information to memory for future recall.
- Be concise and direct in your responses.
- If unsure, recall from memory first.
- When creating content, use proper slugs and metadata.
PROMPT;
    }
}
