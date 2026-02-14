<?php

/**
 * Restore Command
 * 
 * Restores backups of database and files.
 * 
 * Requirements: 15.3, 15.4
 */

declare(strict_types=1);

namespace Framework\Backup;

use Framework\Database\Connection;
use ZipArchive;

class RestoreCommand
{
    private Connection $connection;
    private string $backupPath;
    
    public function __construct(Connection $connection, ?string $backupPath = null)
    {
        $this->connection = $connection;
        $this->backupPath = $backupPath ?? BASE_PATH . '/storage/backups';
    }
    
    /**
     * Restore from backup file
     *
     * @param string $backupFile Path to backup zip file
     * @param array $options Restore options
     * @return bool
     */
    public function execute(string $backupFile, array $options = []): bool
    {
        if (!file_exists($backupFile)) {
            throw new \RuntimeException("Backup file not found: {$backupFile}");
        }
        
        // Validate backup integrity
        if (!$this->validateBackup($backupFile)) {
            throw new \RuntimeException("Backup validation failed");
        }
        
        $tempDir = $this->backupPath . '/restore_' . uniqid();
        
        try {
            // Extract backup
            $this->extractBackup($backupFile, $tempDir);
            
            // Read manifest
            $manifest = $this->readManifest($tempDir);
            
            // Restore database
            if ($options['restore_database'] ?? true) {
                $this->restoreDatabase($tempDir, $manifest);
            }
            
            // Restore files
            if ($options['restore_files'] ?? true) {
                $this->restoreFiles($tempDir, $manifest);
            }
            
            // Clean up
            $this->removeDirectory($tempDir);
            
            return true;
            
        } catch (\Exception $e) {
            // Clean up on error
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            throw $e;
        }
    }
    
    /**
     * Validate backup integrity
     *
     * @param string $backupFile
     * @return bool
     */
    public function validateBackup(string $backupFile): bool
    {
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile) !== true) {
            return false;
        }
        
        // Check for required files
        $requiredFiles = ['manifest.json', 'database.sql'];
        
        foreach ($requiredFiles as $file) {
            if ($zip->locateName($file) === false) {
                $zip->close();
                return false;
            }
        }
        
        $zip->close();
        return true;
    }
    
    /**
     * Extract backup to temporary directory
     *
     * @param string $backupFile
     * @param string $targetDir
     * @return void
     */
    private function extractBackup(string $backupFile, string $targetDir): void
    {
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile) !== true) {
            throw new \RuntimeException("Failed to open backup file");
        }
        
        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            throw new \RuntimeException("Failed to extract backup");
        }
        
        $zip->close();
    }
    
    /**
     * Read backup manifest
     *
     * @param string $backupDir
     * @return array
     */
    private function readManifest(string $backupDir): array
    {
        $manifestFile = $backupDir . '/manifest.json';
        
        if (!file_exists($manifestFile)) {
            throw new \RuntimeException("Manifest file not found");
        }
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        
        if (!$manifest) {
            throw new \RuntimeException("Invalid manifest file");
        }
        
        return $manifest;
    }
    
    /**
     * Restore database from SQL file
     *
     * @param string $backupDir
     * @param array $manifest
     * @return void
     */
    private function restoreDatabase(string $backupDir, array $manifest): void
    {
        $sqlFile = $backupDir . '/' . $manifest['files']['database'];
        
        if (!file_exists($sqlFile)) {
            throw new \RuntimeException("Database backup file not found");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
        );
        
        // Execute each statement
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->connection->execute($statement . ';');
            }
        }
    }
    
    /**
     * Restore files from backup
     *
     * @param string $backupDir
     * @param array $manifest
     * @return void
     */
    private function restoreFiles(string $backupDir, array $manifest): void
    {
        $filesDir = $backupDir . '/files';
        
        if (!is_dir($filesDir)) {
            return; // No files to restore
        }
        
        // Restore uploads
        if (isset($manifest['files']['uploads']) && $manifest['files']['uploads']) {
            $source = $backupDir . '/' . $manifest['files']['uploads'];
            $destination = BASE_PATH . '/public/uploads';
            
            if (is_dir($source)) {
                $this->copyDirectory($source, $destination);
            }
        }
        
        // Restore plugins
        if (isset($manifest['files']['plugins']) && $manifest['files']['plugins']) {
            $source = $backupDir . '/' . $manifest['files']['plugins'];
            $destination = BASE_PATH . '/plugins';
            
            if (is_dir($source)) {
                $this->copyDirectory($source, $destination);
            }
        }
        
        // Restore themes
        if (isset($manifest['files']['themes']) && $manifest['files']['themes']) {
            $source = $backupDir . '/' . $manifest['files']['themes'];
            $destination = BASE_PATH . '/themes';
            
            if (is_dir($source)) {
                $this->copyDirectory($source, $destination);
            }
        }
    }
    
    /**
     * Copy directory recursively
     *
     * @param string $source
     * @param string $destination
     * @return void
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $files = scandir($source);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $sourcePath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;
            
            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }
    
    /**
     * Remove directory recursively
     *
     * @param string $dir
     * @return void
     */
    private function removeDirectory(string $dir): void
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
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
