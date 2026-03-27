<?php

/**
 * System Requirements Checker
 * 
 * Validates system requirements for framework installation.
 * 
 * Requirements: 10.1, 10.6
 */

declare(strict_types=1);

namespace ChimeraNoWP\Install;

class SystemRequirements
{
    /**
     * Check all system requirements
     *
     * @return array Array of requirement checks with status
     */
    public function checkAll(): array
    {
        return [
            'php_version' => $this->checkPhpVersion(),
            'pdo_extension' => $this->checkPdoExtension(),
            'pdo_mysql' => $this->checkPdoMysql(),
            'mbstring' => $this->checkMbstring(),
            'json' => $this->checkJson(),
            'fileinfo' => $this->checkFileinfo(),
            'gd' => $this->checkGd(),
            'writable_storage' => $this->checkWritableStorage(),
            'writable_cache' => $this->checkWritableCache(),
            'mod_rewrite' => $this->checkModRewrite(),
        ];
    }
    
    /**
     * Check if all requirements are met
     *
     * @return bool
     */
    public function allRequirementsMet(): bool
    {
        $checks = $this->checkAll();
        
        foreach ($checks as $check) {
            if (!$check['met']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get list of missing requirements
     *
     * @return array
     */
    public function getMissingRequirements(): array
    {
        $checks = $this->checkAll();
        $missing = [];
        
        foreach ($checks as $name => $check) {
            if (!$check['met']) {
                $missing[] = [
                    'name' => $name,
                    'message' => $check['message'],
                ];
            }
        }
        
        return $missing;
    }
    
    /**
     * Check PHP version (>= 8.1)
     *
     * @return array
     */
    private function checkPhpVersion(): array
    {
        $required = '8.1.0';
        $current = PHP_VERSION;
        $met = version_compare($current, $required, '>=');
        
        return [
            'met' => $met,
            'required' => $required,
            'current' => $current,
            'message' => $met 
                ? "PHP version {$current} meets requirement (>= {$required})"
                : "PHP version {$current} does not meet requirement (>= {$required})",
        ];
    }
    
    /**
     * Check PDO extension
     *
     * @return array
     */
    private function checkPdoExtension(): array
    {
        $met = extension_loaded('pdo');
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'PDO extension is installed'
                : 'PDO extension is required but not installed',
        ];
    }
    
    /**
     * Check PDO MySQL driver
     *
     * @return array
     */
    private function checkPdoMysql(): array
    {
        $met = extension_loaded('pdo_mysql');
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'PDO MySQL driver is installed'
                : 'PDO MySQL driver is required but not installed',
        ];
    }
    
    /**
     * Check mbstring extension
     *
     * @return array
     */
    private function checkMbstring(): array
    {
        $met = extension_loaded('mbstring');
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'mbstring extension is installed'
                : 'mbstring extension is required but not installed',
        ];
    }
    
    /**
     * Check JSON extension
     *
     * @return array
     */
    private function checkJson(): array
    {
        $met = extension_loaded('json');
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'JSON extension is installed'
                : 'JSON extension is required but not installed',
        ];
    }
    
    /**
     * Check fileinfo extension
     *
     * @return array
     */
    private function checkFileinfo(): array
    {
        $met = extension_loaded('fileinfo');
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'fileinfo extension is installed'
                : 'fileinfo extension is required for file uploads',
        ];
    }
    
    /**
     * Check GD extension
     *
     * @return array
     */
    private function checkGd(): array
    {
        $met = extension_loaded('gd');
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'GD extension is installed'
                : 'GD extension is required for image processing',
        ];
    }
    
    /**
     * Check if storage directory is writable
     *
     * @return array
     */
    private function checkWritableStorage(): array
    {
        $path = BASE_PATH . '/storage';
        $met = is_dir($path) && is_writable($path);
        
        return [
            'met' => $met,
            'path' => $path,
            'message' => $met 
                ? "Storage directory ({$path}) is writable"
                : "Storage directory ({$path}) is not writable",
        ];
    }
    
    /**
     * Check if cache directory is writable
     *
     * @return array
     */
    private function checkWritableCache(): array
    {
        $path = BASE_PATH . '/storage/cache';
        
        // Create if doesn't exist
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        
        $met = is_dir($path) && is_writable($path);
        
        return [
            'met' => $met,
            'path' => $path,
            'message' => $met 
                ? "Cache directory ({$path}) is writable"
                : "Cache directory ({$path}) is not writable",
        ];
    }
    
    /**
     * Check if mod_rewrite is available (Apache)
     *
     * @return array
     */
    private function checkModRewrite(): array
    {
        // Check if running on Apache
        $isApache = strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false;
        
        if (!$isApache) {
            return [
                'met' => true,
                'message' => 'Not running on Apache, mod_rewrite check skipped',
            ];
        }
        
        // Check if mod_rewrite is loaded
        $met = function_exists('apache_get_modules') 
            ? in_array('mod_rewrite', apache_get_modules()) 
            : true; // Assume true if we can't check
        
        return [
            'met' => $met,
            'message' => $met 
                ? 'mod_rewrite is enabled'
                : 'mod_rewrite is required but not enabled',
        ];
    }
}
