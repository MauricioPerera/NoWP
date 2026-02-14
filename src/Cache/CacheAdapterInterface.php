<?php

/**
 * Cache Adapter Interface
 * 
 * Defines the contract for cache adapters (APCu, Redis, Memcached, File).
 * 
 * Requirements: 11.5, 11.6
 */

declare(strict_types=1);

namespace Framework\Cache;

interface CacheAdapterInterface
{
    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @return mixed Value or null if not found
     */
    public function get(string $key): mixed;
    
    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    
    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool;
    
    /**
     * Flush all cache entries
     *
     * @return bool Success status
     */
    public function flush(): bool;
}
