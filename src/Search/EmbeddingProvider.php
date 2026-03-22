<?php

/**
 * Embedding Provider Interface
 *
 * Abstraction for generating text embeddings.
 * Supports Ollama (local), Cloudflare Workers AI, OpenAI, or any HTTP API.
 */

declare(strict_types=1);

namespace Framework\Search;

interface EmbeddingProviderInterface
{
    /**
     * Generate an embedding vector for text.
     *
     * @param string $text Text to embed.
     * @return float[] Vector of floats.
     * @throws \RuntimeException If embedding fails.
     */
    public function embed(string $text): array;

    /**
     * Get the output dimensions of this provider.
     */
    public function dimensions(): int;
}

/**
 * Ollama embedding provider — local, offline, zero cost.
 */
class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private string $model = 'embeddinggemma',
        private string $host = 'http://localhost:11434',
        private int $dimensions = 768,
    ) {}

    public function embed(string $text): array
    {
        $response = @file_get_contents($this->host . '/api/embeddings', false,
            stream_context_create(['http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/json',
                'content' => json_encode(['model' => $this->model, 'prompt' => $text]),
                'timeout' => 30,
            ]])
        );

        if (false === $response) {
            throw new \RuntimeException("Failed to connect to Ollama at {$this->host}. Is it running?");
        }

        $data = json_decode($response, true);
        if (!isset($data['embedding'])) {
            throw new \RuntimeException('Ollama returned no embedding: ' . ($data['error'] ?? 'unknown error'));
        }

        return $data['embedding'];
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}

/**
 * HTTP-based embedding provider — works with any OpenAI-compatible API.
 * Supports: Cloudflare Workers AI, OpenAI, Mistral, etc.
 */
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

        // Navigate output path (e.g., "result.data.0")
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
