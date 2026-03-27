<?php

/**
 * File Cache Adapter
 * 
 * Cache adapter using filesystem storage.
 * Fallback option when no other cache system is available.
 * 
 * Requirements: 11.6
 */

declare(strict_types=1);

namespace ChimeraNoWP\Cache;

class FileAdapter implements CacheAdapterInterface
{
    private string $path;
    private string $prefix;
    
    public function __construct(string $path, string $prefix = 'framework_')
    {
        $this->path = rtrim($path, '/');
        $this->prefix = $prefix;
        
        // Ensure cache directory exists
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }
    
    public function get(string $key): mixed
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }
        
        $data = unserialize($content, ['allowed_classes' => [
            \ChimeraNoWP\Content\Content::class,
            \ChimeraNoWP\Content\ContentType::class,
            \ChimeraNoWP\Content\ContentStatus::class,
            \ChimeraNoWP\Content\Media::class,
            \ChimeraNoWP\Auth\User::class,
            \ChimeraNoWP\Auth\UserRole::class,
            \DateTime::class,
        ]]);
        
        // Check if expired
        if ($data['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $data['value'];
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];
        
        return file_put_contents($file, serialize($data), LOCK_EX) !== false;
    }
    
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    public function flush(): bool
    {
        $files = glob($this->path . '/*');
        
        if ($files === false) {
            return false;
        }
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Get file path for cache key
     *
     * @param string $key
     * @return string
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($this->prefix . $key);
        return $this->path . '/' . $hash;
    }
    
    /**
     * Check if file cache is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return true; // Always available
    }
}
