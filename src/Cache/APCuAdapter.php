<?php

/**
 * APCu Cache Adapter
 * 
 * Cache adapter using APCu (Alternative PHP Cache - User Cache).
 * Preferred for shared hosting environments.
 * 
 * Requirements: 11.5, 11.6
 */

declare(strict_types=1);

namespace Framework\Cache;

class APCuAdapter implements CacheAdapterInterface
{
    private string $prefix;
    
    public function __construct(string $prefix = 'framework_')
    {
        $this->prefix = $prefix;
    }
    
    public function get(string $key): mixed
    {
        $value = apcu_fetch($this->prefix . $key, $success);
        return $success ? $value : null;
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return apcu_store($this->prefix . $key, $value, $ttl);
    }
    
    public function delete(string $key): bool
    {
        return apcu_delete($this->prefix . $key);
    }
    
    public function flush(): bool
    {
        return apcu_clear_cache();
    }
    
    /**
     * Check if APCu is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('apcu') && ini_get('apc.enabled');
    }
}
