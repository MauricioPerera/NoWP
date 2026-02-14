<?php

/**
 * Cache Configuration
 */

return [
    // Default cache driver
    // Options: apcu, redis, memcached, file
    // Set to 'auto' to automatically detect available driver
    'default' => env('CACHE_DRIVER', 'auto'),
    
    // Cache drivers configuration
    'drivers' => [
        'apcu' => [
            'driver' => 'apcu',
        ],
        
        'redis' => [
            'driver' => 'redis',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => env('REDIS_DB', 0),
        ],
        
        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],
        
        'file' => [
            'driver' => 'file',
            'path' => BASE_PATH . '/storage/cache',
        ],
    ],
    
    // Default TTL (time to live) in seconds
    'ttl' => env('CACHE_TTL', 3600),
    
    // Cache key prefix
    'prefix' => env('CACHE_PREFIX', 'framework_'),
    
    // Auto-detection order (when driver is 'auto')
    'detection_order' => ['apcu', 'redis', 'memcached', 'file'],
];
