<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\LLM;

final class WorkersAIProvider implements ProviderInterface
{
    private string $currentModel;

    public function __construct(
        private readonly string $accountId,
        private readonly string $apiToken,
        string $model = '@cf/ibm-granite/granite-4.0-h-micro',
    ) {
        $this->currentModel = $model;
    }

    public function name(): string { return 'workers-ai'; }

    /**
     * Normalize a message array to CF Workers AI format.
     * CF granite requires: content must be string (not null), assistant messages must have content.
     */
    private function sanitizeMessage(array $msg): array
    {
        // Ensure content is always a string (CF rejects missing/null content)
        if (!isset($msg['content']) || $msg['content'] === null) {
            $msg['content'] = '';
        }

        // Ensure content is a string (CF rejects arrays for non-multimodal models)
        if (is_array($msg['content'])) {
            // Flatten OpenAI multi-part content to plain text
            $parts = array_map(fn($p) => is_array($p) ? ($p['text'] ?? '') : (string)$p, $msg['content']);
            $msg['content'] = implode('', $parts);
        }

        return $msg;
    }
    public function model(): string { return $this->currentModel; }
    public function setModel(string $model): void { $this->currentModel = $model; }

    public function chat(array $messages, array $tools = []): LLMResponse
    {
        $payload = ['messages' => array_map(fn($m) => $this->sanitizeMessage($m->jsonSerialize()), $messages)];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $payload['max_tokens'] = 2048;
        $payload['temperature'] = 0.3;

        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$this->currentModel}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_HTTPHEADER      => ["Authorization: Bearer {$this->apiToken}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS      => json_encode($payload),
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 120,
            CURLOPT_SSL_VERIFYPEER  => false,   // dev: Windows curl often has no CA bundle
            CURLOPT_SSL_VERIFYHOST  => 0,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            error_log("WorkersAI curl error: {$curlError}");
            return new LLMResponse(content: "API connection failed: {$curlError}", toolCalls: null, finishReason: 'error');
        }

        if ($httpCode >= 400) {
            $errData = json_decode($response, true);
            $errMsg = $errData['errors'][0]['message'] ?? ($errData['error'] ?? "HTTP {$httpCode}");
            error_log("WorkersAI HTTP {$httpCode}: {$errMsg} | body: " . substr($response, 0, 500));
            return new LLMResponse(content: "WorkersAI error {$httpCode}: {$errMsg}", toolCalls: null, finishReason: 'stop');
        }

        $raw = json_decode($response, true) ?? [];
        return MessageNormalizer::normalize($raw);
    }
}
