<?php

/**
 * Database Configuration
 */

return [
    // Default database connection
    'default' => env('DB_CONNECTION', 'mysql'),
    
    // Database connections
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'framework'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'strict' => true,
            'engine' => null,
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
    
    // Connection retry settings
    'retry' => [
        'attempts' => 3,
        'delay' => 100, // milliseconds
    ],
    
    // Migrations
    'migrations' => [
        'table' => 'migrations',
        'path' => BASE_PATH . '/migrations',
    ],
];
