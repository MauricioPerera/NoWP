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

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (false === $response || !empty($error)) {
            throw new \RuntimeException("AI API request failed to {$this->url}: {$error}");
        }

        if ($httpCode >= 400) {
            $errData = json_decode($response, true);
            $msg = $errData['errors'][0]['message'] ?? $errData['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("AI API error: {$msg}");
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

        // Clean messages: ensure content is always a string, remove tool-specific fields
        foreach ($messages as $msg) {
            $clean = ['role' => $msg['role'] ?? 'user'];

            // Ensure content is a string
            $content = $msg['content'] ?? '';
            if (is_array($content)) {
                $content = json_encode($content);
            }
            if ($content === null || $content === '') {
                // Skip messages with no content (e.g., tool_calls-only assistant messages)
                // unless they have tool_calls
                if (!empty($msg['tool_calls'])) {
                    $clean['content'] = '';
                    $clean['tool_calls'] = $msg['tool_calls'];
                    $result[] = $clean;
                    continue;
                }
                // Skip empty messages
                continue;
            }

            $clean['content'] = (string) $content;

            // Pass through tool_call_id for tool response messages
            if (isset($msg['tool_call_id'])) {
                $clean['tool_call_id'] = $msg['tool_call_id'];
            }
            if (isset($msg['tool_calls'])) {
                $clean['tool_calls'] = $msg['tool_calls'];
            }

            $result[] = $clean;
        }

        return $result;
    }
}
