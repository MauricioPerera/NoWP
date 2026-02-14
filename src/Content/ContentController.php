<?php

/**
 * Content Controller
 * 
 * REST API controller for content management.
 * Provides endpoints for CRUD operations on content.
 * 
 * Requirements: 1.1, 2.1, 2.2, 2.3
 */

declare(strict_types=1);

namespace Framework\Content;

use Framework\Core\Request;
use Framework\Core\Response;

class ContentController
{
    private ContentService $contentService;
    
    public function __construct(ContentService $contentService)
    {
        $this->contentService = $contentService;
    }
    
    /**
     * List all content with optional filters
     * 
     * GET /api/contents
     * Query params: type, status, author_id, limit, offset, order_by, order_direction
     * 
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            $filters = [
                'type' => $request->query('type'),
                'status' => $request->query('status'),
                'author_id' => $request->query('author_id'),
                'limit' => $request->query('limit', 20),
                'offset' => $request->query('offset', 0),
                'order_by' => $request->query('order_by', 'created_at'),
                'order_direction' => $request->query('order_direction', 'desc'),
            ];
            
            // Remove null values
            $filters = array_filter($filters, fn($value) => $value !== null);
            
            $contents = $this->contentService->getAllContent($filters);
            
            // Convert to array format
            $data = array_map(fn($content) => $content->toArray(), $contents);
            
            return Response::success($data, 'Contents retrieved successfully');
        } catch (\Exception $e) {
            return Response::error(
                'Failed to retrieve contents: ' . $e->getMessage(),
                'CONTENT_RETRIEVAL_ERROR',
                500
            );
        }
    }
    
    /**
     * Get a single content item by ID
     * 
     * GET /api/contents/{id}
     * 
     * @param Request $request
     * @param int $id Content ID
     * @return Response
     */
    public function show(Request $request, int $id): Response
    {
        try {
            $content = $this->contentService->getContent($id);
            
            if (!$content) {
                return Response::error(
                    "Content with ID {$id} not found",
                    'CONTENT_NOT_FOUND',
                    404
                );
            }
            
            return Response::success($content->toArray(), 'Content retrieved successfully');
        } catch (\Exception $e) {
            return Response::error(
                'Failed to retrieve content: ' . $e->getMessage(),
                'CONTENT_RETRIEVAL_ERROR',
                500
            );
        }
    }
    
    /**
     * Create new content
     * 
     * POST /api/contents
     * Body: { title, slug?, content, type, status?, author_id, parent_id?, custom_fields? }
     * 
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        try {
            $data = $request->getBody();
            
            // Validate required fields
            if (empty($data['title'])) {
                return Response::error(
                    'Title is required',
                    'VALIDATION_ERROR',
                    400,
                    ['field' => 'title']
                );
            }
            
            if (empty($data['type'])) {
                return Response::error(
                    'Type is required',
                    'VALIDATION_ERROR',
                    400,
                    ['field' => 'type']
                );
            }
            
            // Get author from authenticated user or request
            if (empty($data['author_id'])) {
                $user = $request->user();
                if ($user) {
                    $data['author_id'] = $user['id'];
                } else {
                    return Response::error(
                        'Author ID is required',
                        'VALIDATION_ERROR',
                        400,
                        ['field' => 'author_id']
                    );
                }
            }
            
            $content = $this->contentService->createContent($data);
            
            return Response::success(
                $content->toArray(),
                'Content created successfully',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return Response::error(
                $e->getMessage(),
                'VALIDATION_ERROR',
                400
            );
        } catch (\Exception $e) {
            return Response::error(
                'Failed to create content: ' . $e->getMessage(),
                'CONTENT_CREATION_ERROR',
                500
            );
        }
    }
    
    /**
     * Update existing content
     * 
     * PUT /api/contents/{id}
     * Body: { title?, slug?, content?, type?, status?, parent_id?, custom_fields? }
     * 
     * @param Request $request
     * @param int $id Content ID
     * @return Response
     */
    public function update(Request $request, int $id): Response
    {
        try {
            $data = $request->getBody();
            
            if (empty($data)) {
                return Response::error(
                    'No data provided for update',
                    'VALIDATION_ERROR',
                    400
                );
            }
            
            $content = $this->contentService->updateContent($id, $data);
            
            return Response::success(
                $content->toArray(),
                'Content updated successfully'
            );
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'not found')) {
                return Response::error(
                    $e->getMessage(),
                    'CONTENT_NOT_FOUND',
                    404
                );
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return Response::error(
                $e->getMessage(),
                'VALIDATION_ERROR',
                400
            );
        } catch (\Exception $e) {
            return Response::error(
                'Failed to update content: ' . $e->getMessage(),
                'CONTENT_UPDATE_ERROR',
                500
            );
        }
    }
    
    /**
     * Delete content
     * 
     * DELETE /api/contents/{id}
     * 
     * @param Request $request
     * @param int $id Content ID
     * @return Response
     */
    public function destroy(Request $request, int $id): Response
    {
        try {
            $deleted = $this->contentService->deleteContent($id);
            
            if (!$deleted) {
                return Response::error(
                    "Content with ID {$id} not found",
                    'CONTENT_NOT_FOUND',
                    404
                );
            }
            
            return Response::success(
                null,
                'Content deleted successfully',
                204
            );
        } catch (\RuntimeException $e) {
            return Response::error(
                $e->getMessage(),
                'CONTENT_DELETION_ERROR',
                400
            );
        } catch (\Exception $e) {
            return Response::error(
                'Failed to delete content: ' . $e->getMessage(),
                'CONTENT_DELETION_ERROR',
                500
            );
        }
    }
    
    /**
     * Get content by slug
     * 
     * GET /api/contents/slug/{slug}
     * 
     * @param Request $request
     * @param string $slug Content slug
     * @return Response
     */
    public function showBySlug(Request $request, string $slug): Response
    {
        try {
            $content = $this->contentService->getContentBySlug($slug);
            
            if (!$content) {
                return Response::error(
                    "Content with slug '{$slug}' not found",
                    'CONTENT_NOT_FOUND',
                    404
                );
            }
            
            return Response::success($content->toArray(), 'Content retrieved successfully');
        } catch (\Exception $e) {
            return Response::error(
                'Failed to retrieve content: ' . $e->getMessage(),
                'CONTENT_RETRIEVAL_ERROR',
                500
            );
        }
    }
    
    /**
     * Get version history for content
     * 
     * GET /api/contents/{id}/versions
     * 
     * @param Request $request
     * @param int $id Content ID
     * @return Response
     */
    public function versions(Request $request, int $id): Response
    {
        try {
            $versions = $this->contentService->getVersionHistory($id);
            
            return Response::success($versions, 'Version history retrieved successfully');
        } catch (\Exception $e) {
            return Response::error(
                'Failed to retrieve version history: ' . $e->getMessage(),
                'VERSION_RETRIEVAL_ERROR',
                500
            );
        }
    }
    
    /**
     * Restore content to a specific version
     * 
     * POST /api/contents/{id}/versions/{versionId}/restore
     * 
     * @param Request $request
     * @param int $id Content ID
     * @param int $versionId Version ID
     * @return Response
     */
    public function restoreVersion(Request $request, int $id, int $versionId): Response
    {
        try {
            $content = $this->contentService->restoreVersion($id, $versionId);
            
            return Response::success(
                $content->toArray(),
                'Content restored to version successfully'
            );
        } catch (\RuntimeException $e) {
            return Response::error(
                $e->getMessage(),
                'VERSION_NOT_FOUND',
                404
            );
        } catch (\Exception $e) {
            return Response::error(
                'Failed to restore version: ' . $e->getMessage(),
                'VERSION_RESTORE_ERROR',
                500
            );
        }
    }
}
