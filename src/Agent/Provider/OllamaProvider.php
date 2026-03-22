<?php

declare(strict_types=1);

namespace Framework\Agent\Provider;

/**
 * Ollama AI Provider — local, offline, free.
 * Supports tool calling with compatible models (llama3.1+, qwen2.5+, etc).
 */
class OllamaProvider implements AIProviderInterface
{
    public function __construct(
        private string $model = 'llama3.1',
        private string $host = 'http://localhost:11434',
        private float $temperature = 0.7,
    ) {}

    public function chat(array $messages, string $systemPrompt = '', array $tools = []): array
    {
        $payload = [
            'model'    => $this->model,
            'messages' => $this->buildMessages($messages, $systemPrompt),
            'stream'   => false,
            'options'  => ['temperature' => $this->temperature],
        ];

        if (!empty($tools)) {
            $payload['tools'] = $this->formatTools($tools);
        }

        $response = $this->request('/api/chat', $payload);

        return [
            'content'    => $response['message']['content'] ?? null,
            'tool_calls' => $response['message']['tool_calls'] ?? [],
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

    private function formatTools(array $tools): array
    {
        return array_map(fn(array $tool) => [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'parameters'  => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ], $tools);
    }

    private function request(string $path, array $body): array
    {
        $response = @file_get_contents($this->host . $path, false,
            stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode($body),
                'timeout' => 120,
            ]])
        );

        if (false === $response) {
            throw new \RuntimeException("Failed to connect to Ollama at {$this->host}");
        }

        return json_decode($response, true) ?: [];
    }
}
