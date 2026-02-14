<?php

use Framework\Backup\BackupCommand;
use Framework\Database\Connection;

beforeEach(function () {
    $this->connection = new Connection([
        'default' => 'sqlite',
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ],
            ],
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 100,
        ],
    ]);
    
    // Create test table
    $this->connection->execute("
        CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            value TEXT
        )
    ");
    
    $this->connection->execute("
        INSERT INTO test_table (id, name, value) VALUES (1, 'test', 'data')
    ");
    
    $this->backupPath = __DIR__ . '/../../fixtures/backups';
    if (!is_dir($this->backupPath)) {
        mkdir($this->backupPath, 0755, true);
    }
    
    $this->command = new BackupCommand($this->connection, $this->backupPath);
});

afterEach(function () {
    // Clean up backup files
    if (is_dir($this->backupPath)) {
        $files = glob($this->backupPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                removeDirectory($file);
            }
        }
    }
});

test('creates backup with timestamp', function () {
    $backupFile = $this->command->execute(['include_files' => false]);
    
    expect($backupFile)->toBeString();
    expect(file_exists($backupFile))->toBeTrue();
    expect($backupFile)->toContain('backup_');
    expect($backupFile)->toEndWith('.zip');
});

test('backup contains database sql file', function () {
    $backupFile = $this->command->execute(['include_files' => false]);
    
    $zip = new ZipArchive();
    $zip->open($backupFile);
    
    expect($zip->locateName('database.sql'))->not->toBeFalse();
    
    $zip->close();
});

test('backup contains manifest file', function () {
    $backupFile = $this->command->execute(['include_files' => false]);
    
    $zip = new ZipArchive();
    $zip->open($backupFile);
    
    expect($zip->locateName('manifest.json'))->not->toBeFalse();
    
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    expect($manifest)->toHaveKey('version');
    expect($manifest)->toHaveKey('created_at');
    expect($manifest)->toHaveKey('php_version');
    
    $zip->close();
});

test('database backup includes table structure', function () {
    $backupFile = $this->command->execute(['include_files' => false]);
    
    $zip = new ZipArchive();
    $zip->open($backupFile);
    
    $sql = $zip->getFromName('database.sql');
    expect($sql)->toContain('CREATE TABLE');
    expect($sql)->toContain('test_table');
    
    $zip->close();
});

test('database backup includes table data', function () {
    $backupFile = $this->command->execute(['include_files' => false]);
    
    $zip = new ZipArchive();
    $zip->open($backupFile);
    
    $sql = $zip->getFromName('database.sql');
    expect($sql)->toContain('INSERT INTO');
    expect($sql)->toContain('test');
    expect($sql)->toContain('data');
    
    $zip->close();
});

test('cleans up temporary directory after backup', function () {
    $backupFile = $this->command->execute(['include_files' => false]);
    
    $tempDirs = glob($this->backupPath . '/backup_*');
    $tempDirs = array_filter($tempDirs, 'is_dir');
    
    expect($tempDirs)->toBeEmpty();
});

test('cleans up on backup failure', function () {
    // Create a command with invalid connection to force failure
    $badConnection = new Connection([
        'driver' => 'sqlite',
        'database' => '/invalid/path/database.db',
    ]);
    
    $command = new BackupCommand($badConnection, $this->backupPath);
    
    try {
        $command->execute(['include_files' => false]);
    } catch (\Exception $e) {
        // Expected to fail
    }
    
    $tempDirs = glob($this->backupPath . '/backup_*');
    $tempDirs = array_filter($tempDirs, 'is_dir');
    
    expect($tempDirs)->toBeEmpty();
});
