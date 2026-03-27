<?php

/**
 * Content Repository
 * 
 * Provides CRUD operations for content management with support for
 * filtering and pagination using the QueryBuilder.
 * 
 * Requirements: 2.3
 */

declare(strict_types=1);

namespace ChimeraNoWP\Content;

use ChimeraNoWP\Database\QueryBuilder;
use ChimeraNoWP\Database\Connection;
use DateTime;

class ContentRepository
{
    private Connection $connection;
    private ?CustomFieldRepository $customFieldRepository = null;
    
    public function __construct(Connection $connection, ?CustomFieldRepository $customFieldRepository = null)
    {
        $this->connection = $connection;
        $this->customFieldRepository = $customFieldRepository;
    }
    
    /**
     * Create a new query builder instance
     *
     * @return QueryBuilder
     */
    private function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }
    
    /**
     * Find content by ID
     *
     * @param int $id Content ID
     * @return Content|null
     */
    public function find(int $id): ?Content
    {
        $row = $this->newQuery()
            ->table('contents')
            ->where('id', $id)
            ->first();
        
        return $row ? $this->mapToContent($row) : null;
    }
    
    /**
     * Find content by slug
     *
     * @param string $slug Content slug
     * @return Content|null
     */
    public function findBySlug(string $slug): ?Content
    {
        $row = $this->newQuery()
            ->table('contents')
            ->where('slug', $slug)
            ->first();
        
        return $row ? $this->mapToContent($row) : null;
    }
    
    /**
     * Find all content with optional filters and pagination
     *
     * @param array $filters Optional filters (type, status, author_id, locale, limit, offset)
     * @return array
     */
    public function findAll(array $filters = []): array
    {
        $query = $this->newQuery()->table('contents');
        
        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }
        
        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }
        
        // Filter by locale
        if (isset($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }
        
        // Apply ordering
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);
        
        // Apply pagination
        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }
        
        if (isset($filters['offset'])) {
            $query->offset((int) $filters['offset']);
        }
        
        $rows = $query->get();
        
        // Eager load custom fields to avoid N+1 queries
        $contentIds = array_column($rows, 'id');
        $customFieldsMap = [];
        
        if (!empty($contentIds) && $this->customFieldRepository) {
            $customFieldsMap = $this->customFieldRepository->getFieldsForMultipleContents($contentIds);
        }
        
        return array_map(function($row) use ($customFieldsMap) {
            return $this->mapToContent($row, $customFieldsMap[(int)$row['id']] ?? []);
        }, $rows);
    }
    
    /**
     * Create new content
     *
     * @param array $data Content data
     * @return Content
     */
    public function create(array $data): Content
    {
        $now = new DateTime();
        
        $insertData = [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['content'] ?? '',
            'type' => $data['type'],
            'status' => $data['status'] ?? ContentStatus::DRAFT->value,
            'author_id' => $data['author_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'locale' => $data['locale'] ?? 'en',
            'translation_group' => $data['translation_group'] ?? null,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'published_at' => $data['published_at'] ?? null,
        ];
        
        $id = $this->newQuery()->table('contents')->insert($insertData);
        
        return new Content(
            id: $id,
            title: $insertData['title'],
            slug: $insertData['slug'],
            content: $insertData['content'],
            type: ContentType::from($insertData['type']),
            status: ContentStatus::from($insertData['status']),
            authorId: $insertData['author_id'],
            parentId: $insertData['parent_id'],
            customFields: [],
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $insertData['published_at'] ? new DateTime($insertData['published_at']) : null,
            locale: $insertData['locale'],
            translationGroup: $insertData['translation_group']
        );
    }
    
    /**
     * Update existing content
     *
     * @param int $id Content ID
     * @param array $data Content data to update
     * @return Content
     */
    public function update(int $id, array $data): Content
    {
        $updateData = [];
        
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        
        if (isset($data['slug'])) {
            $updateData['slug'] = $data['slug'];
        }
        
        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }
        
        if (isset($data['type'])) {
            $updateData['type'] = $data['type'];
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        if (isset($data['parent_id'])) {
            $updateData['parent_id'] = $data['parent_id'];
        }
        
        if (isset($data['published_at'])) {
            $updateData['published_at'] = $data['published_at'];
        }

        if (isset($data['locale'])) {
            $updateData['locale'] = $data['locale'];
        }

        if (isset($data['translation_group'])) {
            $updateData['translation_group'] = $data['translation_group'];
        }

        $updateData['updated_at'] = (new DateTime())->format('Y-m-d H:i:s');
        
        $this->newQuery()
            ->table('contents')
            ->where('id', $id)
            ->update($updateData);
        
        $content = $this->find($id);
        
        if (!$content) {
            throw new \RuntimeException("Content with ID {$id} not found after update");
        }
        
        return $content;
    }
    
    /**
     * Delete content
     *
     * @param int $id Content ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        $affected = $this->newQuery()
            ->table('contents')
            ->where('id', $id)
            ->delete();
        
        return $affected > 0;
    }
    
    /**
     * Count content with optional filters
     *
     * @param array $filters Optional filters (type, status, author_id)
     * @return int
     */
    public function count(array $filters = []): int
    {
        $query = $this->newQuery()->table('contents');
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }
        
        return $query->count();
    }
    
    /**
     * Map database row to Content object
     *
     * @param array $row Database row
     * @param array|null $customFields Pre-loaded custom fields (optional)
     * @return Content
     */
    private function mapToContent(array $row, ?array $customFields = null): Content
    {
        // Load custom fields if not provided and repository is available
        if ($customFields === null && $this->customFieldRepository) {
            $customFields = $this->customFieldRepository->getFieldsForContent((int) $row['id']);
        }
        
        return new Content(
            id: (int) $row['id'],
            title: $row['title'],
            slug: $row['slug'],
            content: $row['content'],
            type: ContentType::from($row['type']),
            status: ContentStatus::from($row['status']),
            authorId: (int) $row['author_id'],
            parentId: $row['parent_id'] ? (int) $row['parent_id'] : null,
            customFields: $customFields ?? [],
            createdAt: new DateTime($row['created_at']),
            updatedAt: new DateTime($row['updated_at']),
            publishedAt: $row['published_at'] ? new DateTime($row['published_at']) : null,
            locale: $row['locale'] ?? 'en',
            translationGroup: $row['translation_group'] ?? null
        );
    }
}
