<?php

/**
 * Image Processor
 * 
 * Processes images: resize, crop, and generate thumbnails.
 * Uses Intervention/Image library for image manipulation.
 * 
 * Requirements: 6.3
 */

declare(strict_types=1);

namespace ChimeraNoWP\Storage;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessor
{
    private ImageManager $manager;
    private array $defaultSizes;
    
    /**
     * Create a new ImageProcessor instance
     * 
     * @param array $defaultSizes Default thumbnail sizes
     */
    public function __construct(array $defaultSizes = [])
    {
        $this->manager = new ImageManager(new Driver());
        $this->defaultSizes = $defaultSizes ?: [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 300, 'height' => 300],
            'large' => ['width' => 1024, 'height' => 1024],
        ];
    }
    
    /**
     * Resize an image
     * 
     * @param string $path Path to image file
     * @param int $width Target width
     * @param int $height Target height
     * @param bool $maintainAspectRatio Maintain aspect ratio
     * @return string Path to resized image
     */
    public function resize(
        string $path,
        int $width,
        int $height,
        bool $maintainAspectRatio = true
    ): string {
        if (!file_exists($path)) {
            throw new \RuntimeException("Image file not found: {$path}");
        }
        
        $image = $this->manager->read($path);
        
        if ($maintainAspectRatio) {
            $image->scale(width: $width, height: $height);
        } else {
            $image->resize($width, $height);
        }
        
        // Generate output path
        $outputPath = $this->generateResizedPath($path, $width, $height);
        
        // Save resized image
        $image->save($outputPath);
        
        return $outputPath;
    }
    
    /**
     * Crop an image
     * 
     * @param string $path Path to image file
     * @param int $width Crop width
     * @param int $height Crop height
     * @param string $position Crop position (center, top-left, etc.)
     * @return string Path to cropped image
     */
    public function crop(
        string $path,
        int $width,
        int $height,
        string $position = 'center'
    ): string {
        if (!file_exists($path)) {
            throw new \RuntimeException("Image file not found: {$path}");
        }
        
        $image = $this->manager->read($path);
        
        // Crop from center by default
        $image->cover($width, $height, $position);
        
        // Generate output path
        $outputPath = $this->generateCroppedPath($path, $width, $height);
        
        // Save cropped image
        $image->save($outputPath);
        
        return $outputPath;
    }
    
    /**
     * Generate thumbnails in multiple sizes
     * 
     * @param string $path Path to image file
     * @param array|null $sizes Thumbnail sizes (null = use defaults)
     * @return array Array of generated thumbnail paths
     */
    public function generateThumbnails(string $path, ?array $sizes = null): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Image file not found: {$path}");
        }
        
        $sizes = $sizes ?? $this->defaultSizes;
        $thumbnails = [];

        // Read source image once and clone for each thumbnail
        $sourceImage = $this->manager->read($path);

        foreach ($sizes as $name => $dimensions) {
            $width = $dimensions['width'] ?? 150;
            $height = $dimensions['height'] ?? 150;

            $image = clone $sourceImage;

            // Scale to fit within dimensions while maintaining aspect ratio
            $image->scale(width: $width, height: $height);
            
            // Generate thumbnail path
            $thumbnailPath = $this->generateThumbnailPath($path, $width, $height);
            
            // Save thumbnail
            $image->save($thumbnailPath);
            
            $thumbnails[$name] = $thumbnailPath;
        }
        
        return $thumbnails;
    }
    
    /**
     * Get image dimensions
     * 
     * @param string $path Path to image file
     * @return array Array with 'width' and 'height' keys
     */
    public function getDimensions(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Image file not found: {$path}");
        }
        
        $image = $this->manager->read($path);
        
        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }
    
    /**
     * Check if file is a valid image
     * 
     * @param string $path Path to file
     * @return bool
     */
    public function isValidImage(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        
        try {
            $this->manager->read($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate path for resized image
     * 
     * @param string $originalPath Original image path
     * @param int $width Width
     * @param int $height Height
     * @return string Generated path
     */
    private function generateResizedPath(string $originalPath, int $width, int $height): string
    {
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        return "{$directory}/{$filename}-{$width}x{$height}.{$extension}";
    }
    
    /**
     * Generate path for cropped image
     * 
     * @param string $originalPath Original image path
     * @param int $width Width
     * @param int $height Height
     * @return string Generated path
     */
    private function generateCroppedPath(string $originalPath, int $width, int $height): string
    {
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        return "{$directory}/{$filename}-{$width}x{$height}-cropped.{$extension}";
    }
    
    /**
     * Generate path for thumbnail
     * 
     * @param string $originalPath Original image path
     * @param int $width Width
     * @param int $height Height
     * @return string Generated path
     */
    private function generateThumbnailPath(string $originalPath, int $width, int $height): string
    {
        $pathInfo = pathinfo($originalPath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'];
        
        return "{$directory}/{$filename}-{$width}x{$height}.{$extension}";
    }
}
