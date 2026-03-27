<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\LLM;

final class OpenAIProvider implements ProviderInterface
{
    private string $currentModel;

    public function __construct(
        private readonly string $apiKey,
        string $model = 'gpt-4o-mini',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly float $temperature = 0.7,
    ) {
        $this->currentModel = $model;
    }

    public function name(): string { return 'openai'; }
    public function model(): string { return $this->currentModel; }
    public function setModel(string $model): void { $this->currentModel = $model; }

    public function chat(array $messages, array $tools = []): LLMResponse
    {
        $payload = [
            'model' => $this->currentModel,
            'messages' => array_map(fn($m) => $m->jsonSerialize(), $messages),
            'max_tokens' => 4096,
            'temperature' => $this->temperature,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $ch = curl_init(rtrim($this->baseUrl, '/') . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            $errDetail = $response ? json_decode($response, true)['error']['message'] ?? "HTTP {$httpCode}" : 'Connection failed';
            return new LLMResponse(
                content: "OpenAI error: {$errDetail}",
                toolCalls: null,
                finishReason: 'error',
            );
        }

        $raw = json_decode($response, true) ?? [];
        return MessageNormalizer::normalize($raw);
    }
}
