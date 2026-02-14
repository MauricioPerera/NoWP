<?php

/**
 * Memcached Cache Adapter
 * 
 * Cache adapter using Memcached for distributed caching.
 * 
 * Requirements: 11.5, 11.6
 */

declare(strict_types=1);

namespace Framework\Cache;

use Memcached;

class MemcachedAdapter implements CacheAdapterInterface
{
    private Memcached $memcached;
    private string $prefix;
    
    public function __construct(array $config = [], string $prefix = 'framework_')
    {
        $this->memcached = new Memcached();
        $this->prefix = $prefix;
        
        $servers = $config['servers'] ?? [
            ['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]
        ];
        
        foreach ($servers as $server) {
            $this->memcached->addServer(
                $server['host'],
                $server['port'],
                $server['weight'] ?? 100
            );
        }
    }
    
    public function get(string $key): mixed
    {
        $value = $this->memcached->get($this->prefix . $key);
        
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return null;
        }
        
        return $value;
    }
    
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($this->prefix . $key, $value, $ttl);
    }
    
    public function delete(string $key): bool
    {
        return $this->memcached->delete($this->prefix . $key);
    }
    
    public function flush(): bool
    {
        return $this->memcached->flush();
    }
    
    /**
     * Check if Memcached is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('memcached');
    }
}
