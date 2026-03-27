<?php

/**
 * Search Controller
 *
 * REST API endpoints for semantic search.
 *
 * Endpoints:
 *   GET  /api/search?q=text&type=post&limit=10    Semantic search
 *   GET  /api/search/stats                         Index statistics
 *   POST /api/search/reindex                       Rebuild index (admin only)
 */

declare(strict_types=1);

namespace ChimeraNoWP\Search;

use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Content\ContentRepository;

class SearchController
{
    public function __construct(
        private SearchService $search,
        private ContentRepository $contentRepo,
    ) {}

    /**
     * GET /api/search?q=text&type=post&limit=10
     */
    public function search(Request $request): array
    {
        $query = $request->query('q', '');
        $type  = $request->query('type', '');
        $limit = min((int) $request->query('limit', 10), 50);

        if ('' === $query) {
            return ['error' => 'Query parameter "q" is required.', 'status' => 400];
        }

        if ($type) {
            $results = $this->search->hybridSearch($type, $query, $limit, ['status' => 'published']);
        } else {
            $results = $this->search->searchAll($query, $limit);
        }

        // Enrich with content data
        $enriched = [];
        foreach ($results as $r) {
            $content = $this->contentRepo->find((int) $r['id']);
            if (!$content) {
                continue;
            }

            $enriched[] = [
                'id'       => $content->getId(),
                'title'    => $content->getTitle(),
                'slug'     => $content->getSlug(),
                'type'     => $content->getType(),
                'excerpt'  => mb_substr(strip_tags($content->getBody()), 0, 200),
                'score'    => $r['score'],
                'stages'   => $r['stages'] ?? null,
            ];
        }

        return [
            'query'   => $query,
            'results' => $enriched,
            'count'   => count($enriched),
        ];
    }

    /**
     * GET /api/search/stats
     */
    public function stats(): array
    {
        return $this->search->stats();
    }

    /**
     * POST /api/search/reindex
     * Rebuilds the entire search index from database content.
     */
    public function reindex(): array
    {
        $types    = ['post', 'page'];
        $indexed  = 0;
        $errors   = 0;

        foreach ($types as $type) {
            $contents = $this->contentRepo->findAll([
                'type'   => $type,
                'status' => 'published',
                'limit'  => 10000,
            ]);

            foreach ($contents as $content) {
                try {
                    $this->search->indexContent($content);
                    $indexed++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        }

        return [
            'indexed' => $indexed,
            'errors'  => $errors,
            'stats'   => $this->search->stats(),
        ];
    }
}
