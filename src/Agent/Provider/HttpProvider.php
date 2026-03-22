<?php

declare(strict_types=1);

namespace Framework\Agent\Provider;

/**
 * HTTP AI Provider — works with any OpenAI-compatible API.
 * Supports: OpenAI, Anthropic (via proxy), OpenRouter, Cloudflare, Mistral, etc.
 */
class HttpProvider implements AIProviderInterface
{
    public function __construct(
        private string $url,
        private string $apiKey,
        private string $model,
        private float $temperature = 0.7,
        private int $maxTokens = 4096,
    ) {}

    public function chat(array $messages, string $systemPrompt = '', array $tools = []): array
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $this->buildMessages($messages, $systemPrompt),
            'temperature' => $this->temperature,
            'max_tokens'  => $this->maxTokens,
        ];

        if (!empty($tools)) {
            $payload['tools'] = array_map(fn($t) => [
                'type'     => 'function',
                'function' => [
                    'name'        => $t['name'],
                    'description' => $t['description'],
                    'parameters'  => $t['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ],
            ], $tools);
        }

        $response = @file_get_contents($this->url, false,
            stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$this->apiKey}",
                'content' => json_encode($payload),
                'timeout' => 120,
            ]])
        );

        if (false === $response) {
            throw new \RuntimeException("AI API request failed to {$this->url}");
        }

        $data    = json_decode($response, true);
        $choice  = $data['choices'][0] ?? [];
        $message = $choice['message'] ?? [];

        return [
            'content'    => $message['content'] ?? null,
            'tool_calls' => $message['tool_calls'] ?? [],
        ];
    }

    private function buildMessages(array $messages, string $systemPrompt): array
    {
        $result = [];
        if ($systemPrompt) {
            $result[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        return array_merge($result, $messages);
    }
}
