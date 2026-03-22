<?php

declare(strict_types=1);

namespace Framework\Agent\Provider;

/**
 * AI Provider Interface — generates text and handles tool calls.
 */
interface AIProviderInterface
{
    /**
     * Send messages and get a response (may include tool calls).
     *
     * @param array  $messages       Chat messages [{role, content}].
     * @param string $systemPrompt   System instruction.
     * @param array  $tools          Tool definitions for function calling.
     * @return array{content: ?string, tool_calls: array}
     */
    public function chat(array $messages, string $systemPrompt = '', array $tools = []): array;
}
