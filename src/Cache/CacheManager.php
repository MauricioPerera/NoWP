<?php

/**
 * Cache Manager
 * 
 * Provides caching functionality with support for multiple adapters.
 * Implements remember pattern and cache invalidation.
 * 
 * Requirements: 11.1, 11.2
 */

declare(strict_types=1);

namespace Framework\Cache;

class CacheManager
{
    private ?CacheAdapterInterface $adapter = null;
    private array $tags = [];
    
    public function __construct(?CacheAdapterInterface $adapter = null)
    {
        $this->adapter = $adapter ?? $this->detectAdapter();
    }
    
    /**
     * Auto-detect and create best available cache adapter
     *
     * @return CacheAdapterInterface
     */
    private function detectAdapter(): CacheAdapterInterface
    {
        $config = $this->loadConfig();
        $prefix = $config['prefix'] ?? 'framework_';
        
        // If specific driver is configured, use it
        if (isset($config['default']) && $config['default'] !== 'auto') {
            return $this->createAdapter($config['default'], $config, $prefix);
        }
        
        // Auto-detect in order of preference: APCu > Redis > Memcached > File
        $detectionOrder = $config['detection_order'] ?? ['apcu', 'redis', 'memcached', 'file'];
        
        foreach ($detectionOrder as $driver) {
            if ($this->isDriverAvailable($driver)) {
                return $this->createAdapter($driver, $config, $prefix);
            }
        }
        
        // Fallback to NullCacheAdapter
        return new NullCacheAdapter();
    }
    
    /**
     * Check if a cache driver is available
     *
     * @param string $driver
     * @return bool
     */
    private function isDriverAvailable(string $driver): bool
    {
        return match ($driver) {
            'apcu' => APCuAdapter::isAvailable(),
            'redis' => RedisAdapter::isAvailable(),
            'memcached' => MemcachedAdapter::isAvailable(),
            'file' => FileAdapter::isAvailable(),
            default => false,
        };
    }
    
    /**
     * Create cache adapter instance
     *
     * @param string $driver
     * @param array $config
     * @param string $prefix
     * @return CacheAdapterInterface
     */
    private function createAdapter(string $driver, array $config, string $prefix): CacheAdapterInterface
    {
        $driverConfig = $config['drivers'][$driver] ?? [];
        
        return match ($driver) {
            'apcu' => new APCuAdapter($prefix),
            'redis' => new RedisAdapter($driverConfig, $prefix),
            'memcached' => new MemcachedAdapter($driverConfig, $prefix),
            'file' => new FileAdapter($driverConfig['path'] ?? BASE_PATH . '/storage/cache', $prefix),
            default => new NullCacheAdapter(),
        };
    }
    
    /**
     * Load cache configuration
     *
     * @return array
     */
    private function loadConfig(): array
    {
        $configFile = BASE_PATH . '/config/cache.php';
        
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        return [
            'default' => 'auto',
            'prefix' => 'framework_',
            'detection_order' => ['apcu', 'redis', 'memcached', 'file'],
            'drivers' => [
                'file' => ['path' => BASE_PATH . '/storage/cache']
            ]
        ];
    }
    
    /**
     * Get value from cache or execute callback and store result
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Callback to execute if cache miss
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->adapter->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->adapter->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Set cache tags for next operation
     *
     * @param array $tags Tags to associate with cache entries
     * @return self
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }
    
    /**
     * Invalidate cache by tags or key
     *
     * @param string|array $tagsOrKey Tags or key to invalidate
     * @return void
     */
    public function invalidate(string|array $tagsOrKey): void
    {
        if (is_string($tagsOrKey)) {
            $this->adapter->delete($tagsOrKey);
        } else {
            // For now, simple implementation - just delete by tag pattern
            foreach ($tagsOrKey as $tag) {
                $this->adapter->delete($tag);
            }
        }
    }
    
    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->adapter->get($key) !== null;
    }
    
    /**
     * Get value from cache with default
     *
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->adapter->get($key);
        return $value !== null ? $value : $default;
    }
    
    /**
     * Set value in cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->adapter->set($key, $value, $ttl);
    }
    
    /**
     * Delete value from cache
     *
     * @param string $key Cache key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->adapter->delete($key);
    }
    
    /**
     * Flush all cache entries
     *
     * @return bool
     */
    public function flush(): bool
    {
        return $this->adapter->flush();
    }
    
    /**
     * Get the underlying cache adapter
     *
     * @return CacheAdapterInterface
     */
    public function getAdapter(): CacheAdapterInterface
    {
        return $this->adapter;
    }
}
