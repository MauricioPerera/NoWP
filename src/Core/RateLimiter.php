<?php

/**
 * Rate Limiter
 * 
 * Implements rate limiting using cache storage.
 * 
 * Requirements: 12.4
 */

declare(strict_types=1);

namespace Framework\Core;

use Framework\Cache\CacheManager;

class RateLimiter
{
    private CacheManager $cache;
    private string $prefix = 'rate_limit:';
    
    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * Check if rate limit is exceeded
     *
     * @param string $key Unique identifier (e.g., IP address, user ID)
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decaySeconds Time window in seconds
     * @return bool True if rate limit exceeded
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $attempts = $this->attempts($key);
        
        return $attempts >= $maxAttempts;
    }
    
    /**
     * Increment attempts counter
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int Current number of attempts
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $cacheKey = $this->prefix . $key;
        
        $attempts = (int)$this->cache->get($cacheKey, 0);
        $attempts++;
        
        $this->cache->set($cacheKey, $attempts, $decaySeconds);
        
        return $attempts;
    }
    
    /**
     * Get current number of attempts
     *
     * @param string $key
     * @return int
     */
    public function attempts(string $key): int
    {
        $cacheKey = $this->prefix . $key;
        
        return (int)$this->cache->get($cacheKey, 0);
    }
    
    /**
     * Reset attempts counter
     *
     * @param string $key
     * @return void
     */
    public function resetAttempts(string $key): void
    {
        $cacheKey = $this->prefix . $key;
        $this->cache->delete($cacheKey);
    }
    
    /**
     * Get seconds until rate limit resets
     *
     * @param string $key
     * @param int $decaySeconds
     * @return int
     */
    public function availableIn(string $key, int $decaySeconds): int
    {
        $cacheKey = $this->prefix . $key;
        
        // If no attempts, available immediately
        if (!$this->cache->has($cacheKey)) {
            return 0;
        }
        
        // Return decay seconds as approximation
        // (actual TTL tracking would require additional cache storage)
        return $decaySeconds;
    }
    
    /**
     * Clear all rate limit data
     *
     * @return void
     */
    public function clear(): void
    {
        // Note: This would require cache adapter support for prefix-based deletion
        // For now, individual keys must be cleared using resetAttempts()
    }
}
