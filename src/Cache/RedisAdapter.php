<?php

/**
 * Redis Cache Adapter
 * 
 * Cache adapter using Redis for distributed caching.
 * 
 * Requirements: 11.5, 11.6
 */

declare(strict_types=1);

namespace Framework\Cache;

use Redis;

class RedisAdapter implements CacheAdapterInterface
{
    private Redis $redis;
    private string $prefix;
    
    public function __construct(array $config = [], string $prefix = 'framework_')
    {
        $this->redis = new Redis();
        $this->prefix = $prefix;
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        
        $this->redis->connect($host, $port);
        
        if ($password) {
            $this->redis->auth($password);
        }
        
        if ($database > 0) {
            $this->redis->select($database);
        }
    }
    
    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        
        if ($value === false) {
            return null;
        }
        
        return unserialize($value);
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            serialize($value)
        );
    }
    
    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }
    
    public function flush(): bool
    {
        return $this->redis->flushDB();
    }
    
    /**
     * Check if Redis is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('redis');
    }
}
