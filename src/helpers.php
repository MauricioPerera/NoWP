<?php

/**
 * Global Helper Functions
 */

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Get environment variable value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = [];
        
        // Load config files on first access
        if (empty($config)) {
            $configPath = BASE_PATH . '/config';
            foreach (glob($configPath . '/*.php') as $file) {
                $name = basename($file, '.php');
                $config[$name] = require $file;
            }
        }
        
        // Parse dot notation
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the application
     *
     * @param string $path
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path
     *
     * @param string $path
     * @return string
     */
    function public_path(string $path = ''): string
    {
        return PUBLIC_PATH . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path
     *
     * @param string $path
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        return BASE_PATH . '/storage' . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}

if (!function_exists('__')) {
    /**
     * Translate a key
     *
     * @param string $key Translation key
     * @param array $replacements Placeholder replacements
     * @param string|null $locale Specific locale
     * @return string
     */
    function __(string $key, array $replacements = [], ?string $locale = null): string
    {
        static $translator = null;
        
        if ($translator === null) {
            $translator = new \Framework\Core\TranslationManager();
        }
        
        return $translator->translate($key, $replacements, $locale);
    }
}

if (!function_exists('trans')) {
    /**
     * Translate a key (alias for __)
     *
     * @param string $key Translation key
     * @param array $replacements Placeholder replacements
     * @param string|null $locale Specific locale
     * @return string
     */
    function trans(string $key, array $replacements = [], ?string $locale = null): string
    {
        return __($key, $replacements, $locale);
    }
}
