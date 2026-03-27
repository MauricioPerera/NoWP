<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\LLM;

final class OllamaProvider implements ProviderInterface
{
    private string $currentModel;

    public function __construct(
        string $model = 'qwen2.5:7b',
        private readonly string $host = 'http://localhost:11434',
        private readonly float $temperature = 0.7,
    ) {
        $this->currentModel = $model;
    }

    public function name(): string { return 'ollama'; }
    public function model(): string { return $this->currentModel; }
    public function setModel(string $model): void { $this->currentModel = $model; }

    public function chat(array $messages, array $tools = []): LLMResponse
    {
        $payload = [
            'model' => $this->currentModel,
            'messages' => array_map(fn($m) => $m->jsonSerialize(), $messages),
            'stream' => false,
            'options' => [
                'temperature' => $this->temperature,
                'num_predict' => 2048,
            ],
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $ch = curl_init(rtrim($this->host, '/') . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return new LLMResponse(
                content: "Ollama error (HTTP {$httpCode}): Connection to {$this->host} failed",
                toolCalls: null,
                finishReason: 'error',
            );
        }

        $raw = json_decode($response, true) ?? [];

        // Ollama returns {message: {role, content, tool_calls?}, ...}
        // Normalize to OpenAI-like format for MessageNormalizer
        if (isset($raw['message'])) {
            $normalized = [
                'choices' => [[
                    'message' => $raw['message'],
                    'finish_reason' => $raw['done'] ?? true ? 'stop' : 'length',
                ]],
                'usage' => [
                    'prompt_tokens' => $raw['prompt_eval_count'] ?? 0,
                    'completion_tokens' => $raw['eval_count'] ?? 0,
                ],
            ];
            return MessageNormalizer::normalize($normalized);
        }

        return MessageNormalizer::normalize($raw);
    }
}
