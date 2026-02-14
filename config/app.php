<?php

/**
 * Application Configuration
 */

return [
    // Application name
    'name' => env('APP_NAME', 'WordPress Alternative Framework'),
    
    // Environment: production, staging, development
    'env' => env('APP_ENV', 'production'),
    
    // Debug mode (never enable in production)
    'debug' => env('APP_DEBUG', false),
    
    // Application URL
    'url' => env('APP_URL', 'http://localhost'),
    
    // Timezone
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    
    // Locale
    'locale' => env('APP_LOCALE', 'en'),
    
    // Fallback locale
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    
    // JWT Configuration
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'ttl' => env('JWT_TTL', 3600), // 1 hour in seconds
        'refresh_ttl' => env('JWT_REFRESH_TTL', 86400), // 24 hours
        'algorithm' => 'HS256',
    ],
    
    // Security
    'bcrypt_rounds' => env('BCRYPT_ROUNDS', 10),
    
    // Rate Limiting
    'rate_limit' => [
        'login_attempts' => 5,
        'login_decay_minutes' => 15,
    ],
    
    // File Upload
    'upload' => [
        'max_size' => env('UPLOAD_MAX_SIZE', 10485760), // 10MB in bytes
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/zip',
        ],
        'path' => env('UPLOAD_PATH', 'uploads'),
    ],
    
    // Image Processing
    'images' => [
        'thumbnails' => [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [1024, 1024],
        ],
        'quality' => 85,
    ],
    
    // Plugins
    'plugins_path' => BASE_PATH . '/plugins',
    
    // Themes
    'themes_path' => BASE_PATH . '/themes',
    'active_theme' => env('ACTIVE_THEME', 'default'),
    
    // Logging
    'log' => [
        'path' => BASE_PATH . '/storage/logs',
        'level' => env('LOG_LEVEL', 'error'),
    ],
];
