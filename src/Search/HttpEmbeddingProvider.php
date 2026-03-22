<?php

declare(strict_types=1);

namespace Framework\Search;

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

        $context = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$this->apiKey}",
            'content' => json_encode($body),
            'timeout' => 30,
        ]]);

        $response = @file_get_contents($this->url, false, $context);
        if (false === $response) {
            throw new \RuntimeException("Embedding API request failed to {$this->url}");
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
