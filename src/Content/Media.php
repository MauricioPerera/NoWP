<?php

namespace Framework\Content;

use DateTime;

class Media
{
    public function __construct(
        public readonly int $id,
        public string $filename,
        public string $path,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
        public array $thumbnails,
        public int $uploadedBy,
        public DateTime $uploadedAt
    ) {}

    /**
     * Get the public URL for the media file
     */
    public function url(): string
    {
        // Generate URL based on the path
        // Assumes files are stored in public/uploads/
        $baseUrl = rtrim(config('app.url', ''), '/');
        return $baseUrl . '/uploads/' . ltrim($this->path, '/');
    }

    /**
     * Get the URL for a specific thumbnail size
     * 
     * @param string $size The thumbnail size (e.g., '150x150', '300x300')
     * @return string|null The thumbnail URL or null if size doesn't exist
     */
    public function thumbnailUrl(string $size): ?string
    {
        if (!isset($this->thumbnails[$size])) {
            return null;
        }

        $baseUrl = rtrim(config('app.url', ''), '/');
        return $baseUrl . '/uploads/' . ltrim($this->thumbnails[$size], '/');
    }

    /**
     * Convert media to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'path' => $this->path,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'thumbnails' => $this->thumbnails,
            'uploaded_by' => $this->uploadedBy,
            'uploaded_at' => $this->uploadedAt->format('Y-m-d H:i:s'),
            'url' => $this->url(),
        ];
    }

    /**
     * Convert media to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
