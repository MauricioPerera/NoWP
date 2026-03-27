<?php

/**
 * Semantic Search Service
 *
 * Core search engine for NoWP. Vectorizes content on save,
 * provides semantic search via php-vector-store.
 *
 * Features:
 * - Auto-vectorization on content create/update/delete
 * - Semantic search across content types
 * - Matryoshka multi-stage search (128→256→384)
 * - Int8 quantization (392 bytes per vector)
 * - Hybrid search: semantic + keyword combination
 * - Works offline with Ollama or online with any embedding API
 */

declare(strict_types=1);

namespace ChimeraNoWP\Search;

use PHPVectorStore\QuantizedStore;
use PHPVectorStore\VectorStore;

class SearchService
{
    private QuantizedStore|VectorStore $store;
    private EmbeddingProviderInterface $embedder;
    private int $searchDimensions;
    private array $stages;

    public function __construct(
        string $storagePath,
        EmbeddingProviderInterface $embedder,
        int $searchDimensions = 384,
        bool $quantized = true,
    ) {
        $this->embedder         = $embedder;
        $this->searchDimensions = $searchDimensions;
        $this->store            = $quantized
            ? new QuantizedStore($storagePath, $searchDimensions)
            : new VectorStore($storagePath, $searchDimensions);

        // Auto-detect Matryoshka stages
        if ($searchDimensions <= 128) {
            $this->stages = [$searchDimensions];
        } elseif ($searchDimensions <= 256) {
            $this->stages = [128, $searchDimensions];
        } elseif ($searchDimensions <= 384) {
            $this->stages = [128, 256, $searchDimensions];
        } else {
            $this->stages = [128, 384, $searchDimensions];
        }
    }

    // ── Index Operations ───────────────────────────────────────────

    /**
     * Index a content item.
     *
     * @param string $collection Collection name (e.g., 'posts', 'pages').
     * @param string $id         Content ID.
     * @param string $text       Text to vectorize.
     * @param array  $metadata   Optional metadata to store alongside.
     */
    public function index(string $collection, string $id, string $text, array $metadata = []): void
    {
        if ('' === trim($text)) {
            return;
        }

        $vector = $this->embedder->embed($text);
        $vector = array_slice($vector, 0, $this->searchDimensions);

        $this->store->set($collection, $id, $vector, $metadata);
        $this->store->flush();
    }

    /**
     * Index a NoWP Content object.
     * Combines title + body + custom fields into a single embedding.
     */
    public function indexContent(\ChimeraNoWP\Content\Content $content): void
    {
        $text = $content->getTitle() . "\n\n" . strip_tags($content->getBody());

        // Add custom field values to the text for richer embeddings
        $meta = $content->getCustomFields();
        foreach ($meta as $key => $value) {
            if (is_string($value) && '' !== $value) {
                $text .= "\n" . $value;
            }
        }

        $this->index(
            $content->getType(),
            (string) $content->getId(),
            $text,
            [
                'title'  => $content->getTitle(),
                'slug'   => $content->getSlug(),
                'type'   => $content->getType(),
                'status' => $content->getStatus(),
            ]
        );
    }

    /**
     * Remove a content item from the index.
     */
    public function remove(string $collection, string $id): void
    {
        $this->store->remove($collection, $id);
        $this->store->flush();
    }

    /**
     * Remove all vectors for a collection.
     */
    public function dropCollection(string $collection): void
    {
        $this->store->drop($collection);
    }

    // ── Search Operations ──────────────────────────────────────────

    /**
     * Semantic search within a collection.
     *
     * @param string $collection Collection to search.
     * @param string $query      Natural language query.
     * @param int    $limit      Max results.
     * @return array<array{id: string, score: float, metadata: array}>
     */
    public function search(string $collection, string $query, int $limit = 10): array
    {
        $queryVector = $this->embedder->embed($query);
        $queryVector = array_slice($queryVector, 0, $this->searchDimensions);

        return $this->store->matryoshkaSearch($collection, $queryVector, $limit, $this->stages);
    }

    /**
     * Search across all content types.
     *
     * @param string $query  Natural language query.
     * @param int    $limit  Max results.
     * @return array Results with collection info.
     */
    public function searchAll(string $query, int $limit = 10): array
    {
        $queryVector = $this->embedder->embed($query);
        $queryVector = array_slice($queryVector, 0, $this->searchDimensions);

        $collections = $this->store->collections();
        if (empty($collections)) {
            return [];
        }

        $all = [];
        foreach ($collections as $col) {
            foreach ($this->store->matryoshkaSearch($col, $queryVector, $limit, $this->stages) as $r) {
                $r['collection'] = $col;
                $all[] = $r;
            }
        }
        usort($all, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($all, 0, $limit);
    }

    /**
     * Hybrid search: combine semantic results with keyword filtering.
     *
     * @param string $collection Collection to search.
     * @param string $query      Query text.
     * @param int    $limit      Max results.
     * @param array  $filters    Post-search metadata filters (e.g., ['status' => 'published']).
     * @return array Filtered results.
     */
    public function hybridSearch(string $collection, string $query, int $limit = 10, array $filters = []): array
    {
        // Get more candidates than needed to allow filtering
        $candidates = $this->search($collection, $query, $limit * 3);

        if (empty($filters)) {
            return array_slice($candidates, 0, $limit);
        }

        $filtered = [];
        foreach ($candidates as $result) {
            $meta   = $result['metadata'] ?? [];
            $passes = true;

            foreach ($filters as $key => $value) {
                if (!isset($meta[$key]) || $meta[$key] !== $value) {
                    $passes = false;
                    break;
                }
            }

            if ($passes) {
                $filtered[] = $result;
            }

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    // ── Stats ──────────────────────────────────────────────────────

    /**
     * Get search index statistics.
     */
    public function stats(): array
    {
        $storeStats = $this->store->stats();

        return [
            'dimensions'       => $this->searchDimensions,
            'stages'           => $this->stages,
            'quantized'        => $this->store instanceof QuantizedStore,
            'embedder'         => get_class($this->embedder),
            'total_vectors'    => $storeStats['total_vectors'],
            'total_bytes'      => $storeStats['total_bytes'],
            'memory_mb'        => $storeStats['memory_mb'],
            'bytes_per_vector' => $storeStats['bytes_per_vec'],
            'collections'      => $storeStats['collections'],
        ];
    }

    /**
     * Check if the embedding provider is reachable.
     */
    public function isAvailable(): bool
    {
        try {
            $this->embedder->embed('test');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
