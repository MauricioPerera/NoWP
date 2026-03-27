<?php

/**
 * Content Service
 * 
 * Business logic layer for content management.
 * Handles content CRUD operations with hooks, caching, and versioning.
 * 
 * Requirements: 2.1, 2.3, 2.4
 */

declare(strict_types=1);

namespace ChimeraNoWP\Content;

use ChimeraNoWP\Plugin\HookSystem;
use ChimeraNoWP\Cache\CacheManager;
use ChimeraNoWP\Database\Connection;
use DateTime;

class ContentService
{
    private ContentRepository $repository;
    private HookSystem $hooks;
    private CacheManager $cache;
    private Connection $connection;
    private CustomFieldRepository $customFieldRepository;
    
    public function __construct(
        ContentRepository $repository,
        HookSystem $hooks,
        CacheManager $cache,
        Connection $connection,
        CustomFieldRepository $customFieldRepository
    ) {
        $this->repository = $repository;
        $this->hooks = $hooks;
        $this->cache = $cache;
        $this->connection = $connection;
        $this->customFieldRepository = $customFieldRepository;
    }
    
    /**
     * Get content by ID with caching
     *
     * @param int $id Content ID
     * @return Content|null
     */
    public function getContent(int $id): ?Content
    {
        $cacheKey = "content:{$id}";
        
        return $this->cache->remember($cacheKey, 3600, function () use ($id) {
            $content = $this->repository->find($id);
            
            if ($content) {
                // Apply filter hook to allow plugins to modify content
                $content = $this->hooks->applyFilters('content.get', $content);
            }
            
            return $content;
        });
    }
    
    /**
     * Get content by slug with caching
     *
     * @param string $slug Content slug
     * @return Content|null
     */
    public function getContentBySlug(string $slug): ?Content
    {
        $cacheKey = "content:slug:{$slug}";
        
        return $this->cache->remember($cacheKey, 3600, function () use ($slug) {
            $content = $this->repository->findBySlug($slug);
            
            if ($content) {
                $content = $this->hooks->applyFilters('content.get', $content);
            }
            
            return $content;
        });
    }
    
    /**
     * Get all content with filters
     *
     * @param array $filters Optional filters
     * @return array
     */
    public function getAllContent(array $filters = []): array
    {
        $contents = $this->repository->findAll($filters);
        
        // Apply filter hook to each content item
        return array_map(
            fn($content) => $this->hooks->applyFilters('content.get', $content),
            $contents
        );
    }
    
    /**
     * Create new content with versioning
     *
     * @param array $data Content data
     * @return Content
     * @throws \InvalidArgumentException
     */
    public function createContent(array $data): Content
    {
        // Validate required fields
        $this->validateContentData($data);

        // Apply filter hook to allow plugins to modify data before creation
        $data = $this->hooks->applyFilters('content.before_create', $data);

        // Generate slug if not provided
        if (!isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        // Ensure slug is unique
        $data['slug'] = $this->ensureUniqueSlug($data['slug']);

        return $this->connection->transaction(function () use ($data) {
            // Create content
            $content = $this->repository->create($data);

            // Save custom fields if provided
            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $this->customFieldRepository->setFields($content->id, $data['custom_fields']);
            }

            // Create initial version
            $this->createVersion($content);

            // Fire action hook
            $this->hooks->doAction('content.created', $content);

            // Invalidate related caches
            $this->invalidateContentCache($content->id);

            return $content;
        });
    }
    
    /**
     * Update existing content with versioning
     *
     * @param int $id Content ID
     * @param array $data Content data to update
     * @return Content
     * @throws \RuntimeException
     */
    public function updateContent(int $id, array $data): Content
    {
        $existingContent = $this->repository->find($id);

        if (!$existingContent) {
            throw new \RuntimeException("Content with ID {$id} not found");
        }

        // Apply filter hook
        $data = $this->hooks->applyFilters('content.before_update', $data, $existingContent);

        // If slug is being changed, ensure it's unique
        if (isset($data['slug']) && $data['slug'] !== $existingContent->slug) {
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $id);
        }

        return $this->connection->transaction(function () use ($id, $data, $existingContent) {
            // Update content
            $content = $this->repository->update($id, $data);

            // Update custom fields if provided
            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $this->customFieldRepository->setFields($id, $data['custom_fields']);
            }

            // Create new version
            $this->createVersion($content);

            // Fire action hook
            $this->hooks->doAction('content.updated', $content, $existingContent);

            // Invalidate caches
            $this->invalidateContentCache($id);
            $this->cache->invalidate("content:slug:{$existingContent->slug}");
            if (isset($data['slug']) && $data['slug'] !== $existingContent->slug) {
                $this->cache->invalidate("content:slug:{$data['slug']}");
            }

            return $content;
        });
    }
    
    /**
     * Delete content
     *
     * @param int $id Content ID
     * @return bool
     */
    public function deleteContent(int $id): bool
    {
        $content = $this->repository->find($id);

        if (!$content) {
            return false;
        }

        // Apply filter hook to allow plugins to prevent deletion
        $canDelete = $this->hooks->applyFilters('content.can_delete', true, $content);

        if (!$canDelete) {
            throw new \RuntimeException("Content deletion prevented by plugin");
        }

        return $this->connection->transaction(function () use ($id, $content) {
            // Delete versions
            $this->deleteVersions($id);

            // Delete custom fields
            $this->customFieldRepository->deleteAllFields($id);

            // Delete content
            $deleted = $this->repository->delete($id);

            if ($deleted) {
                // Fire action hook
                $this->hooks->doAction('content.deleted', $content);

                // Invalidate caches
                $this->invalidateContentCache($id);
                $this->cache->invalidate("content:slug:{$content->slug}");
            }

            return $deleted;
        });
    }
    
    /**
     * Get content version history
     *
     * @param int $contentId Content ID
     * @return array
     */
    public function getVersionHistory(int $contentId): array
    {
        $query = "SELECT * FROM content_versions WHERE content_id = ? ORDER BY created_at DESC";
        $stmt = $this->connection->getPdo()->prepare($query);
        $stmt->execute([$contentId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Restore content to a specific version
     *
     * @param int $contentId Content ID
     * @param int $versionId Version ID
     * @return Content
     */
    public function restoreVersion(int $contentId, int $versionId): Content
    {
        $query = "SELECT * FROM content_versions WHERE id = ? AND content_id = ?";
        $stmt = $this->connection->getPdo()->prepare($query);
        $stmt->execute([$versionId, $contentId]);
        $version = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$version) {
            throw new \RuntimeException("Version not found");
        }
        
        $data = [
            'title' => $version['title'],
            'content' => $version['content'],
            'status' => $version['status'],
            'slug' => $version['slug'],
            'type' => $version['type'],
        ];
        
        return $this->updateContent($contentId, $data);
    }
    
    /**
     * Create a version snapshot of content
     *
     * @param Content $content Content to version
     * @return void
     */
    private function createVersion(Content $content): void
    {
        // Ensure content_versions table exists (will be created by migration)
        $query = "INSERT INTO content_versions (content_id, title, slug, content, type, status, author_id, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->connection->getPdo()->prepare($query);
            $stmt->execute([
                $content->id,
                $content->title,
                $content->slug,
                $content->content,
                $content->type->value,
                $content->status->value,
                $content->authorId,
                (new DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\PDOException $e) {
            // If table doesn't exist yet, silently fail
            // This allows the service to work before migrations are run
            if (strpos($e->getMessage(), 'no such table') === false && 
                strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e;
            }
        }
    }
    
    /**
     * Delete all versions for content
     *
     * @param int $contentId Content ID
     * @return void
     */
    private function deleteVersions(int $contentId): void
    {
        try {
            $query = "DELETE FROM content_versions WHERE content_id = ?";
            $stmt = $this->connection->getPdo()->prepare($query);
            $stmt->execute([$contentId]);
        } catch (\PDOException $e) {
            // Silently fail if table doesn't exist
            if (strpos($e->getMessage(), 'no such table') === false && 
                strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e;
            }
        }
    }
    
    /**
     * Validate content data
     *
     * @param array $data Content data
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateContentData(array $data): void
    {
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Title is required');
        }
        
        if (empty($data['type'])) {
            throw new \InvalidArgumentException('Type is required');
        }
        
        if (empty($data['author_id'])) {
            throw new \InvalidArgumentException('Author ID is required');
        }
        
        // Validate type is valid
        try {
            ContentType::from($data['type']);
        } catch (\ValueError $e) {
            throw new \InvalidArgumentException('Invalid content type');
        }
        
        // Validate status if provided
        if (isset($data['status'])) {
            try {
                ContentStatus::from($data['status']);
            } catch (\ValueError $e) {
                throw new \InvalidArgumentException('Invalid content status');
            }
        }
    }
    
    /**
     * Generate slug from title
     *
     * @param string $title Content title
     * @return string
     */
    private function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);
        
        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Ensure slug is unique by appending number if needed
     *
     * @param string $slug Desired slug
     * @param int|null $excludeId Content ID to exclude from uniqueness check
     * @return string Unique slug
     */
    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $baseSlug = $slug;
        $query = "SELECT slug FROM contents WHERE (slug = ? OR slug LIKE ?)";
        $bindings = [$slug, $baseSlug . '-%'];
        if ($excludeId !== null) {
            $query .= " AND id != ?";
            $bindings[] = $excludeId;
        }

        $existingSlugs = array_column(
            $this->connection->fetchAll($query, $bindings),
            'slug'
        );

        if (empty($existingSlugs) || !in_array($slug, $existingSlugs)) {
            return $slug;
        }

        $counter = 1;
        while (in_array($baseSlug . '-' . $counter, $existingSlugs)) {
            $counter++;
            if ($counter > 1000) {
                break; // safety cap
            }
        }

        return $baseSlug . '-' . $counter;
    }
    
    /**
     * Invalidate content cache
     *
     * @param int $id Content ID
     * @return void
     */
    private function invalidateContentCache(int $id): void
    {
        $this->cache->invalidate("content:{$id}");
        $this->cache->invalidate(['content']);
    }
}
