<?php

/**
 * Stored File
 * 
 * Represents a file that has been stored in the file system.
 */

declare(strict_types=1);

namespace ChimeraNoWP\Storage;

class StoredFile
{
    public function __construct(
        public readonly string $filename,
        public readonly string $path,
        public readonly string $fullPath,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $url
    ) {}
    
    /**
     * Check if file is an image
     * 
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }
    
    /**
     * Get file extension
     * 
     * @return string
     */
    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }
    
    /**
     * Convert to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'path' => $this->path,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'url' => $this->url,
            'is_image' => $this->isImage(),
        ];
    }
}
