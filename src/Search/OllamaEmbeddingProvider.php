<?php

declare(strict_types=1);

namespace ChimeraNoWP\Search;

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
