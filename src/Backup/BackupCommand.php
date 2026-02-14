<?php

/**
 * Backup Command
 * 
 * Creates backups of database and files.
 * 
 * Requirements: 15.1, 15.2
 */

declare(strict_types=1);

namespace Framework\Backup;

use Framework\Database\Connection;
use ZipArchive;

class BackupCommand
{
    private Connection $connection;
    private string $backupPath;
    
    public function __construct(Connection $connection, ?string $backupPath = null)
    {
        $this->connection = $connection;
        $this->backupPath = $backupPath ?? BASE_PATH . '/storage/backups';
        
        // Ensure backup directory exists
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    /**
     * Create a full backup (database + files)
     *
     * @param array $options Backup options
     * @return string Path to backup file
     */
    public function execute(array $options = []): string
    {
        $timestamp = date('Y-m-d_His');
        $backupName = "backup_{$timestamp}";
        $tempDir = $this->backupPath . '/' . $backupName;
        
        // Create temporary directory
        mkdir($tempDir, 0755, true);
        
        try {
            // Backup database
            $this->backupDatabase($tempDir);
            
            // Backup files if requested
            if ($options['include_files'] ?? true) {
                $this->backupFiles($tempDir);
            }
            
            // Create manifest
            $this->createManifest($tempDir, $options);
            
            // Compress to zip
            $zipPath = $this->compressBackup($tempDir, $backupName);
            
            // Clean up temporary directory
            $this->removeDirectory($tempDir);
            
            return $zipPath;
            
        } catch (\Exception $e) {
            // Clean up on error
            if (is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            throw $e;
        }
    }
    
    /**
     * Backup database to SQL file
     *
     * @param string $targetDir
     * @return void
     */
    private function backupDatabase(string $targetDir): void
    {
        $sqlFile = $targetDir . '/database.sql';
        $tables = $this->getTables();
        
        $sql = "-- Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            $sql .= $this->exportTable($table);
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($sqlFile, $sql);
    }
    
    /**
     * Get list of tables
     *
     * @return array
     */
    private function getTables(): array
    {
        $result = $this->connection->fetchAll("SHOW TABLES");
        $tables = [];
        
        foreach ($result as $row) {
            $tables[] = array_values($row)[0];
        }
        
        return $tables;
    }
    
    /**
     * Export table structure and data
     *
     * @param string $table
     * @return string
     */
    private function exportTable(string $table): string
    {
        $sql = "-- Table: {$table}\n";
        
        // Get CREATE TABLE statement
        $createTable = $this->connection->fetchOne("SHOW CREATE TABLE `{$table}`");
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createTable['Create Table'] . ";\n\n";
        
        // Get table data
        $rows = $this->connection->fetchAll("SELECT * FROM `{$table}`");
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';
            
            foreach ($rows as $row) {
                $values = array_map(function ($value) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return "'" . addslashes($value) . "'";
                }, array_values($row));
                
                $sql .= "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
            }
            
            $sql .= "\n";
        }
        
        return $sql;
    }
    
    /**
     * Backup files
     *
     * @param string $targetDir
     * @return void
     */
    private function backupFiles(string $targetDir): void
    {
        $filesDir = $targetDir . '/files';
        mkdir($filesDir, 0755, true);
        
        // Backup uploads directory
        $uploadsDir = BASE_PATH . '/public/uploads';
        if (is_dir($uploadsDir)) {
            $this->copyDirectory($uploadsDir, $filesDir . '/uploads');
        }
        
        // Backup plugins
        $pluginsDir = BASE_PATH . '/plugins';
        if (is_dir($pluginsDir)) {
            $this->copyDirectory($pluginsDir, $filesDir . '/plugins');
        }
        
        // Backup themes
        $themesDir = BASE_PATH . '/themes';
        if (is_dir($themesDir)) {
            $this->copyDirectory($themesDir, $filesDir . '/themes');
        }
    }
    
    /**
     * Create backup manifest
     *
     * @param string $targetDir
     * @param array $options
     * @return void
     */
    private function createManifest(string $targetDir, array $options): void
    {
        $manifest = [
            'version' => '1.0',
            'created_at' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'framework_version' => '1.0.0',
            'options' => $options,
            'files' => [
                'database' => 'database.sql',
                'uploads' => $options['include_files'] ?? true ? 'files/uploads' : null,
                'plugins' => $options['include_files'] ?? true ? 'files/plugins' : null,
                'themes' => $options['include_files'] ?? true ? 'files/themes' : null,
            ],
        ];
        
        file_put_contents(
            $targetDir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Compress backup to zip file
     *
     * @param string $sourceDir
     * @param string $backupName
     * @return string Path to zip file
     */
    private function compressBackup(string $sourceDir, string $backupName): string
    {
        $zipPath = $this->backupPath . '/' . $backupName . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create zip file: {$zipPath}");
        }
        
        $this->addDirectoryToZip($zip, $sourceDir, '');
        $zip->close();
        
        return $zipPath;
    }
    
    /**
     * Add directory to zip recursively
     *
     * @param ZipArchive $zip
     * @param string $sourceDir
     * @param string $zipPath
     * @return void
     */
    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipPath): void
    {
        $files = scandir($sourceDir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $sourceDir . '/' . $file;
            $zipFilePath = $zipPath ? $zipPath . '/' . $file : $file;
            
            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipFilePath);
                $this->addDirectoryToZip($zip, $filePath, $zipFilePath);
            } else {
                $zip->addFile($filePath, $zipFilePath);
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
