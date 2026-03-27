<?php

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Bridge;

use ChimeraNoWP\Agent\Core\ToolDefinition;
use ChimeraNoWP\Content\ContentService;
use ChimeraNoWP\Content\ContentRepository;
use ChimeraNoWP\Search\SearchService;
use ChimeraNoWP\Storage\FileManager;

/**
 * Exposes NoWP CMS services (Content, Search, Media) as Chimera agent tools.
 */
final class CMSBridge
{
    /**
     * @return ToolDefinition[]
     */
    public static function tools(
        ContentService $content,
        ContentRepository $repo,
        SearchService $search,
        ?FileManager $files = null,
    ): array {
        $tools = [];

        $tools[] = new ToolDefinition(
            name: 'search_content',
            description: 'Search content using semantic search. Returns matching posts, pages, or custom content types.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query text'],
                    'limit' => ['type' => 'integer', 'description' => 'Max results (default 10)'],
                ],
                'required' => ['query'],
            ],
            handler: function (array $args) use ($search): string {
                $query = $args['query'] ?? '';
                $limit = (int) ($args['limit'] ?? 10);
                $results = $search->searchAll($query, $limit);
                return json_encode($results, JSON_UNESCAPED_SLASHES);
            },
            safe: true,
            category: 'cms',
        );

        $tools[] = new ToolDefinition(
            name: 'get_content',
            description: 'Get a specific content item by ID. Returns full content with metadata.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Content ID'],
                ],
                'required' => ['id'],
            ],
            handler: function (array $args) use ($repo): string {
                $item = $repo->find((int) $args['id']);
                return json_encode($item ?: ['error' => 'Content not found'], JSON_UNESCAPED_SLASHES);
            },
            safe: true,
            category: 'cms',
        );

        $tools[] = new ToolDefinition(
            name: 'list_content',
            description: 'List content items with optional filtering by type and status.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'description' => 'Content type: post, page, or custom type'],
                    'status' => ['type' => 'string', 'description' => 'Content status: draft, published, archived'],
                    'page' => ['type' => 'integer', 'description' => 'Page number (default 1)'],
                    'per_page' => ['type' => 'integer', 'description' => 'Items per page (default 20)'],
                ],
                'required' => [],
            ],
            handler: function (array $args) use ($repo): string {
                $filters = [];
                if (!empty($args['type'])) $filters['type'] = $args['type'];
                if (!empty($args['status'])) $filters['status'] = $args['status'];
                $page = (int) ($args['page'] ?? 1);
                $perPage = (int) ($args['per_page'] ?? 20);
                $results = $repo->findAll($filters, $page, $perPage);
                return json_encode($results, JSON_UNESCAPED_SLASHES);
            },
            safe: true,
            category: 'cms',
        );

        $tools[] = new ToolDefinition(
            name: 'create_content',
            description: 'Create new content (post, page, etc). Returns the created content with ID.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'Content title'],
                    'body' => ['type' => 'string', 'description' => 'Content body (HTML or markdown)'],
                    'type' => ['type' => 'string', 'description' => 'Content type: post, page (default: post)'],
                    'status' => ['type' => 'string', 'description' => 'Status: draft, published (default: draft)'],
                    'slug' => ['type' => 'string', 'description' => 'URL slug (auto-generated from title if omitted)'],
                ],
                'required' => ['title', 'body'],
            ],
            handler: function (array $args) use ($content): string {
                try {
                    $result = $content->create([
                        'title' => $args['title'],
                        'body' => $args['body'],
                        'type' => $args['type'] ?? 'post',
                        'status' => $args['status'] ?? 'draft',
                        'slug' => $args['slug'] ?? null,
                    ]);
                    return json_encode($result, JSON_UNESCAPED_SLASHES);
                } catch (\Throwable $e) {
                    return json_encode(['error' => $e->getMessage()]);
                }
            },
            safe: false,
            category: 'cms',
        );

        $tools[] = new ToolDefinition(
            name: 'update_content',
            description: 'Update an existing content item by ID.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Content ID to update'],
                    'title' => ['type' => 'string', 'description' => 'New title'],
                    'body' => ['type' => 'string', 'description' => 'New body content'],
                    'status' => ['type' => 'string', 'description' => 'New status'],
                ],
                'required' => ['id'],
            ],
            handler: function (array $args) use ($content): string {
                try {
                    $id = (int) $args['id'];
                    unset($args['id']);
                    $result = $content->update($id, $args);
                    return json_encode($result, JSON_UNESCAPED_SLASHES);
                } catch (\Throwable $e) {
                    return json_encode(['error' => $e->getMessage()]);
                }
            },
            safe: false,
            category: 'cms',
        );

        if ($files) {
            $tools[] = new ToolDefinition(
                name: 'list_media',
                description: 'List uploaded media files (images, documents, etc).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer', 'description' => 'Page number'],
                    ],
                    'required' => [],
                ],
                handler: function (array $args) use ($files): string {
                    $items = $files->list((int) ($args['page'] ?? 1));
                    return json_encode($items, JSON_UNESCAPED_SLASHES);
                },
                safe: true,
                category: 'cms',
            );
        }

        return $tools;
    }
}
