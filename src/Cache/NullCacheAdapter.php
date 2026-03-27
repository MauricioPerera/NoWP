<?php

/**
 * Null Cache Adapter
 * 
 * No-op cache adapter for when caching is disabled or unavailable.
 * 
 * Requirements: 11.6
 */

declare(strict_types=1);

namespace ChimeraNoWP\Cache;

class NullCacheAdapter implements CacheAdapterInterface
{
    public function get(string $key): mixed
    {
        return null;
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return true;
    }
    
    public function delete(string $key): bool
    {
        return true;
    }
    
    public function flush(): bool
    {
        return true;
    }
}
