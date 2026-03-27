<?php

/**
 * Media Controller
 * 
 * Handles media upload, listing, and deletion via REST API.
 * 
 * Requirements: 6.1, 6.5
 */

declare(strict_types=1);

namespace ChimeraNoWP\Content;

use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Storage\FileManager;
use ChimeraNoWP\Storage\ImageProcessor;
use ChimeraNoWP\Database\Connection;
use ChimeraNoWP\Database\QueryBuilder;

class MediaController
{
    public function __construct(
        private FileManager $fileManager,
        private ImageProcessor $imageProcessor,
        private Connection $connection,
    ) {}

    /**
     * Create a fresh QueryBuilder to avoid state leakage between queries.
     */
    private function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->connection);
    }
    
    /**
     * Upload a media file
     * 
     * POST /api/media
     * 
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request): Response
    {
        try {
            // Get uploaded file from request
            $files = $_FILES ?? [];
            
            if (empty($files['file'])) {
                return Response::json([
                    'error' => 'No file uploaded'
                ], 400);
            }
            
            $file = $files['file'];
            
            // Upload file
            $storedFile = $this->fileManager->upload($file);
            
            // Generate thumbnails if it's an image
            $thumbnails = [];
            if ($storedFile->isImage()) {
                try {
                    $thumbnailPaths = $this->imageProcessor->generateThumbnails($storedFile->fullPath);
                    
                    // Convert full paths to relative paths
                    foreach ($thumbnailPaths as $size => $path) {
                        $thumbnails[$size] = str_replace($storedFile->fullPath, $storedFile->path, $path);
                    }
                } catch (\Exception $e) {
                    // Continue without thumbnails if generation fails
                    $thumbnails = [];
                }
            }
            
            // Get image dimensions if applicable
            $width = null;
            $height = null;
            if ($storedFile->isImage()) {
                try {
                    $dimensions = $this->imageProcessor->getDimensions($storedFile->fullPath);
                    $width = $dimensions['width'];
                    $height = $dimensions['height'];
                } catch (\Exception $e) {
                    // Continue without dimensions
                }
            }
            
            // Get current user ID (from auth middleware)
            $userId = $request->get('user_id', 1); // Default to 1 for testing
            
            // Save to database
            $this->connection->execute(
                "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $storedFile->filename,
                    $storedFile->path,
                    $storedFile->mimeType,
                    $storedFile->size,
                    $width,
                    $height,
                    $userId,
                    date('Y-m-d H:i:s'),
                ]
            );
            
            $mediaId = (int) $this->connection->lastInsertId();
            
            // Create Media object
            $media = new Media(
                id: $mediaId,
                filename: $storedFile->filename,
                path: $storedFile->path,
                mimeType: $storedFile->mimeType,
                size: $storedFile->size,
                width: $width,
                height: $height,
                thumbnails: $thumbnails,
                uploadedBy: $userId,
                uploadedAt: new \DateTime()
            );
            
            return Response::json($media->toArray(), 201);
            
        } catch (\RuntimeException $e) {
            return Response::json([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to upload file'
            ], 500);
        }
    }
    
    /**
     * List all media files
     * 
     * GET /api/media
     * 
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            // Get query parameters
            $page = (int) $request->query('page', 1);
            $perPage = min((int) $request->query('per_page', 20), 100);
            $mimeType = $request->query('mime_type');
            
            $offset = ($page - 1) * $perPage;
            
            // Build query
            $query = $this->newQuery()->table('media')
                ->select(['*'])
                ->orderBy('uploaded_at', 'desc')
                ->limit($perPage)
                ->offset($offset);
            
            // Filter by MIME type if provided
            if ($mimeType) {
                $query->where('mime_type', $mimeType);
            }
            
            $results = $query->get();
            
            // Convert to Media objects
            $mediaList = array_map(function ($row) {
                return new Media(
                    id: $row['id'],
                    filename: $row['filename'],
                    path: $row['path'],
                    mimeType: $row['mime_type'],
                    size: $row['size'],
                    width: $row['width'],
                    height: $row['height'],
                    thumbnails: [], // TODO: Load from separate table if needed
                    uploadedBy: $row['uploaded_by'],
                    uploadedAt: new \DateTime($row['uploaded_at'])
                );
            }, $results);
            
            return Response::json([
                'data' => array_map(fn($m) => $m->toArray(), $mediaList),
                'page' => $page,
                'per_page' => $perPage,
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve media'
            ], 500);
        }
    }
    
    /**
     * Get a single media file
     * 
     * GET /api/media/{id}
     * 
     * @param int $id
     * @return Response
     */
    public function show(int $id): Response
    {
        try {
            $row = $this->newQuery()->table('media')
                ->select(['*'])
                ->where('id', $id)
                ->first();
            
            if (!$row) {
                return Response::json([
                    'error' => 'Media not found'
                ], 404);
            }
            
            $media = new Media(
                id: $row['id'],
                filename: $row['filename'],
                path: $row['path'],
                mimeType: $row['mime_type'],
                size: $row['size'],
                width: $row['width'],
                height: $row['height'],
                thumbnails: [],
                uploadedBy: $row['uploaded_by'],
                uploadedAt: new \DateTime($row['uploaded_at'])
            );
            
            return Response::json($media->toArray());
            
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to retrieve media'
            ], 500);
        }
    }
    
    /**
     * Delete a media file
     * 
     * DELETE /api/media/{id}
     * 
     * @param int $id
     * @return Response
     */
    public function destroy(int $id): Response
    {
        try {
            // Get media record
            $row = $this->newQuery()->table('media')
                ->select(['*'])
                ->where('id', $id)
                ->first();
            
            if (!$row) {
                return Response::json([
                    'error' => 'Media not found'
                ], 404);
            }
            
            // Delete file from storage
            $this->fileManager->delete($row['path']);
            
            // Delete thumbnails if they exist
            // TODO: Load thumbnail paths from database or generate them
            
            // Delete from database
            $this->newQuery()->table('media')
                ->where('id', $id)
                ->delete();
            
            return Response::json([
                'message' => 'Media deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'error' => 'Failed to delete media'
            ], 500);
        }
    }
}
