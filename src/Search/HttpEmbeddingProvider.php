<?php

declare(strict_types=1);

namespace ChimeraNoWP\Search;

class HttpEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private string $url,
        private string $apiKey,
        private string $model = '',
        private int $dimensions = 768,
        private string $inputField = 'text',
        private string $outputPath = 'result.data.0',
    ) {}

    public function embed(string $text): array
    {
        $body = [$this->inputField => [$text]];
        if ($this->model) {
            $body['model'] = $this->model;
        }

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,  // dev: Windows curl has no CA bundle
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $response || !empty($error)) {
            throw new \RuntimeException("Embedding API request failed to {$this->url}: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("Embedding API error (HTTP {$httpCode}): " . substr($response, 0, 200));
        }

        $data = json_decode($response, true);

        $result = $data;
        foreach (explode('.', $this->outputPath) as $key) {
            $result = is_numeric($key) ? ($result[(int)$key] ?? null) : ($result[$key] ?? null);
            if (null === $result) {
                throw new \RuntimeException("Embedding response missing path: {$this->outputPath}");
            }
        }

        return $result;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
