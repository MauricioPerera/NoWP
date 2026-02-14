<?php

/**
 * Test Helper Functions
 * 
 * Shared utility functions for tests.
 */

if (!function_exists('removeDirectory')) {
    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return void
     */
    function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
