<?php

/**
 * Theme Helper Functions
 * 
 * Global helper functions for use in theme templates.
 * 
 * Requirements: 8.4
 */

if (!function_exists('theme_url')) {
    /**
     * Get URL for theme asset
     *
     * @param string $path Asset path relative to theme directory
     * @return string
     */
    function theme_url(string $path): string
    {
        global $themeManager;
        
        if (!$themeManager) {
            return '/themes/default/' . ltrim($path, '/');
        }
        
        $theme = $themeManager->getActiveTheme() ?? 'default';
        return '/themes/' . $theme . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset_url')) {
    /**
     * Get URL for asset with optional versioning
     *
     * @param string $path Asset path
     * @param bool $versioned Add version hash
     * @return string
     */
    function asset_url(string $path, bool $versioned = false): string
    {
        $url = '/' . ltrim($path, '/');
        
        if ($versioned) {
            $fullPath = BASE_PATH . '/public/' . ltrim($path, '/');
            if (file_exists($fullPath)) {
                $hash = substr(md5_file($fullPath), 0, 8);
                $url .= '?v=' . $hash;
            }
        }
        
        return $url;
    }
}

if (!function_exists('site_url')) {
    /**
     * Get site URL
     *
     * @param string $path Optional path to append
     * @return string
     */
    function site_url(string $path = ''): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return $protocol . '://' . $host . '/' . ltrim($path, '/');
    }
}

if (!function_exists('theme_config')) {
    /**
     * Get theme configuration value
     *
     * @param string $key Configuration key (dot notation supported)
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function theme_config(string $key, mixed $default = null): mixed
    {
        global $themeManager;
        
        if (!$themeManager) {
            return $default;
        }
        
        $keys = explode('.', $key);
        $config = $themeManager->getConfig();
        
        if (!$config) {
            return $default;
        }
        
        $value = $config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML entities
     *
     * @param string $value Value to escape
     * @return string
     */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date for display
     *
     * @param DateTime|string $date Date to format
     * @param string $format Date format
     * @return string
     */
    function format_date(DateTime|string $date, string $format = 'F j, Y'): string
    {
        if (is_string($date)) {
            $date = new DateTime($date);
        }
        
        return $date->format($format);
    }
}

if (!function_exists('excerpt')) {
    /**
     * Generate excerpt from content
     *
     * @param string $content Content to excerpt
     * @param int $length Maximum length
     * @param string $more Suffix for truncated content
     * @return string
     */
    function excerpt(string $content, int $length = 150, string $more = '...'): string
    {
        // Strip HTML tags
        $text = strip_tags($content);
        
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        // Truncate at word boundary
        $text = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($text, ' ');
        
        if ($lastSpace !== false) {
            $text = mb_substr($text, 0, $lastSpace);
        }
        
        return $text . $more;
    }
}

if (!function_exists('pluralize')) {
    /**
     * Simple pluralization helper
     *
     * @param int $count Count
     * @param string $singular Singular form
     * @param string|null $plural Plural form (auto-generated if null)
     * @return string
     */
    function pluralize(int $count, string $singular, ?string $plural = null): string
    {
        if ($count === 1) {
            return $count . ' ' . $singular;
        }
        
        $plural = $plural ?? $singular . 's';
        return $count . ' ' . $plural;
    }
}

if (!function_exists('current_url')) {
    /**
     * Get current URL
     *
     * @return string
     */
    function current_url(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . '://' . $host . $uri;
    }
}

if (!function_exists('is_active')) {
    /**
     * Check if current URL matches path
     *
     * @param string $path Path to check
     * @return bool
     */
    function is_active(string $path): bool
    {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
        return str_starts_with($currentPath, $path);
    }
}
