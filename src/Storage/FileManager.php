<?php

/**
 * File Manager
 * 
 * Manages file uploads, storage, and retrieval.
 * Validates file types, generates unique names, and organizes files by date.
 * 
 * Requirements: 6.1, 6.2, 6.4, 6.5
 */

declare(strict_types=1);

namespace Framework\Storage;

class FileManager
{
    private string $uploadPath;
    private string $baseUrl;
    private array $allowedMimeTypes;
    private int $maxFileSize;
    
    /**
     * Create a new FileManager instance
     * 
     * @param string $uploadPath Base path for file uploads
     * @param string $baseUrl Base URL for accessing files
     * @param array $allowedMimeTypes Allowed MIME types (empty = allow all)
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     */
    public function __construct(
        string $uploadPath = 'public/uploads',
        string $baseUrl = '/uploads',
        array $allowedMimeTypes = [],
        int $maxFileSize = 10485760
    ) {
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxFileSize = $maxFileSize;
    }
    
    /**
     * Upload a file
     * 
     * @param array $file Uploaded file array from $_FILES
     * @param array $options Upload options
     * @return StoredFile
     * @throws \RuntimeException If upload fails
     */
    public function upload(array $file, array $options = []): StoredFile
    {
        // Validate file
        $this->validateFile($file);
        
        // Generate unique filename
        $originalName = $file['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = $this->generateUniqueFilename($originalName, $extension);
        
        // Get date-based directory path
        $datePath = $this->getDatePath();
        $fullPath = $this->uploadPath . '/' . $datePath;
        
        // Create directory if it doesn't exist
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        
        // Move uploaded file
        $destination = $fullPath . '/' . $uniqueName;
        
        if (isset($file['tmp_name'])) {
            // Real file upload
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \RuntimeException('Failed to move uploaded file');
            }
        } else {
            // For testing: copy from source path
            if (isset($file['source']) && file_exists($file['source'])) {
                if (!copy($file['source'], $destination)) {
                    throw new \RuntimeException('Failed to copy file');
                }
            } else {
                throw new \RuntimeException('Invalid file upload');
            }
        }
        
        // Get file info
        $mimeType = $this->getMimeType($destination);
        $size = filesize($destination);
        
        return new StoredFile(
            filename: $uniqueName,
            path: $datePath . '/' . $uniqueName,
            fullPath: $destination,
            mimeType: $mimeType,
            size: $size,
            url: $this->url($datePath . '/' . $uniqueName)
        );
    }
    
    /**
     * Delete a file
     * 
     * @param string $path Relative path to file
     * @return bool Success status
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->uploadPath . '/' . ltrim($path, '/');
        
        if (!file_exists($fullPath)) {
            return false;
        }
        
        return unlink($fullPath);
    }
    
    /**
     * Check if a file exists
     * 
     * @param string $path Relative path to file
     * @return bool
     */
    public function exists(string $path): bool
    {
        $fullPath = $this->uploadPath . '/' . ltrim($path, '/');
        return file_exists($fullPath);
    }
    
    /**
     * Get public URL for a file
     * 
     * @param string $path Relative path to file
     * @return string Public URL
     */
    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }
    
    /**
     * Move a file to a new location
     * 
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool Success status
     */
    public function move(string $from, string $to): bool
    {
        $fromPath = $this->uploadPath . '/' . ltrim($from, '/');
        $toPath = $this->uploadPath . '/' . ltrim($to, '/');
        
        if (!file_exists($fromPath)) {
            return false;
        }
        
        // Create destination directory if needed
        $toDir = dirname($toPath);
        if (!is_dir($toDir)) {
            mkdir($toDir, 0755, true);
        }
        
        return rename($fromPath, $toPath);
    }
    
    /**
     * Validate uploaded file
     * 
     * @param array $file File array
     * @throws \RuntimeException If validation fails
     */
    private function validateFile(array $file): void
    {
        // Check for upload errors
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload error: ' . $this->getUploadErrorMessage($file['error']));
        }
        
        // Check file size
        $size = $file['size'] ?? 0;
        if ($size > $this->maxFileSize) {
            throw new \RuntimeException(sprintf(
                'File size (%d bytes) exceeds maximum allowed size (%d bytes)',
                $size,
                $this->maxFileSize
            ));
        }
        
        // Check MIME type if restrictions are set
        if (!empty($this->allowedMimeTypes)) {
            $mimeType = $file['type'] ?? '';
            
            // Also check actual file MIME type if file exists
            if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
                $mimeType = $this->getMimeType($file['tmp_name']);
            } elseif (isset($file['source']) && file_exists($file['source'])) {
                $mimeType = $this->getMimeType($file['source']);
            }
            
            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                throw new \RuntimeException(sprintf(
                    'File type "%s" is not allowed. Allowed types: %s',
                    $mimeType,
                    implode(', ', $this->allowedMimeTypes)
                ));
            }
        }
    }
    
    /**
     * Generate unique filename
     * 
     * @param string $originalName Original filename
     * @param string $extension File extension
     * @return string Unique filename
     */
    private function generateUniqueFilename(string $originalName, string $extension): string
    {
        // Remove extension from original name
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize filename
        $baseName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $baseName);
        $baseName = preg_replace('/-+/', '-', $baseName);
        $baseName = trim($baseName, '-');
        
        // Generate hash for uniqueness
        $hash = substr(md5(uniqid() . $originalName . microtime()), 0, 8);
        
        return $baseName . '-' . $hash . '.' . $extension;
    }
    
    /**
     * Get date-based path (year/month)
     * 
     * @return string Date path
     */
    private function getDatePath(): string
    {
        return date('Y') . '/' . date('m');
    }
    
    /**
     * Get MIME type of a file
     * 
     * @param string $path File path
     * @return string MIME type
     */
    private function getMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            return mime_content_type($path);
        }
        
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mimeType;
        }
        
        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Get upload error message
     * 
     * @param int $errorCode Error code
     * @return string Error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error'
        };
    }
    
    /**
     * Clean up orphaned files
     * 
     * Finds and deletes files that are not referenced in the database.
     * Requirements: 6.6
     * 
     * @param callable $isReferencedCallback Callback to check if file is referenced
     *                                       Should accept file path and return bool
     * @param bool $dryRun If true, only report orphans without deleting
     * @return array List of orphaned files (deleted or found)
     */
    public function cleanOrphans(callable $isReferencedCallback, bool $dryRun = false): array
    {
        $orphans = [];
        
        // Scan upload directory recursively
        $files = $this->scanDirectory($this->uploadPath);
        
        foreach ($files as $file) {
            // Get relative path
            $relativePath = str_replace($this->uploadPath . '/', '', $file);
            
            // Check if file is referenced
            if (!$isReferencedCallback($relativePath)) {
                $orphans[] = $relativePath;
                
                // Delete if not dry run
                if (!$dryRun) {
                    $this->delete($relativePath);
                }
            }
        }
        
        return $orphans;
    }
    
    /**
     * Scan directory recursively for files
     * 
     * @param string $directory Directory to scan
     * @return array List of file paths
     */
    private function scanDirectory(string $directory): array
    {
        $files = [];
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $items = scandir($directory);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep') {
                continue;
            }
            
            $path = $directory . '/' . $item;
            
            if (is_dir($path)) {
                // Recursively scan subdirectories
                $files = array_merge($files, $this->scanDirectory($path));
            } else {
                // Add file to list
                $files[] = $path;
            }
        }
        
        return $files;
    }
}
