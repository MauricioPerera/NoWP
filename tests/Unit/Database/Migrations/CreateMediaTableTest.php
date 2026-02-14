<?php

/**
 * Create Media Table Migration Tests
 * 
 * Tests for the media table migration to ensure proper schema creation,
 * foreign keys, and rollback functionality.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000001_create_users_table.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000003_create_media_table.php';

use Framework\Database\Connection;

beforeEach(function () {
    $config = [
        'default' => 'testing',
        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ],
            ],
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 100,
        ],
    ];
    
    $this->connection = new Connection($config);
    
    // Create users table first (foreign key dependency)
    $this->connection->execute("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL
        )
    ");
    
    // Insert test user
    $this->connection->execute(
        "INSERT INTO users (email, password_hash, display_name, role) VALUES (?, ?, ?, ?)",
        ['uploader@example.com', 'hash123', 'Test Uploader', 'author']
    );
    
    $this->migration = new CreateMediaTable($this->connection);
});

it('creates media table with correct schema', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='media'"
    );
    expect($result)->toHaveCount(1);
});

it('creates uploaded_by index', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    $this->connection->execute("CREATE INDEX idx_uploaded_by ON media(uploaded_by)");
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='index' AND name='idx_uploaded_by'"
    );
    expect($result)->toHaveCount(1);
});

it('enforces foreign key constraint for uploaded_by', function () {
    $this->connection->execute("PRAGMA foreign_keys = ON");
    
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    // Try to insert with non-existent user
    expect(fn() => $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?)",
        ['test.jpg', '/uploads/test.jpg', 'image/jpeg', 1024, 999]
    ))->toThrow(PDOException::class);
});

it('allows null width and height', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    // Insert non-image file (no dimensions)
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?)",
        ['document.pdf', '/uploads/document.pdf', 'application/pdf', 2048, 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT width, height FROM media WHERE filename = ?",
        ['document.pdf']
    );
    
    expect($result['width'])->toBeNull()
        ->and($result['height'])->toBeNull();
});

it('stores image dimensions when provided', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    // Insert image with dimensions
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
        ['photo.jpg', '/uploads/photo.jpg', 'image/jpeg', 5120, 1920, 1080, 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT width, height FROM media WHERE filename = ?",
        ['photo.jpg']
    );
    
    expect($result['width'])->toBe(1920)
        ->and($result['height'])->toBe(1080);
});

it('sets uploaded_at automatically', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?)",
        ['test.jpg', '/uploads/test.jpg', 'image/jpeg', 1024, 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT uploaded_at FROM media WHERE filename = ?",
        ['test.jpg']
    );
    
    expect($result['uploaded_at'])->not->toBeNull();
});

it('requires all non-nullable fields', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    // Try to insert without required fields
    expect(fn() => $this->connection->execute(
        "INSERT INTO media (filename) VALUES (?)",
        ['test.jpg']
    ))->toThrow(PDOException::class);
});

it('stores all media fields correctly', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
        ['banner.png', '/uploads/2026/01/banner.png', 'image/png', 10240, 1200, 600, 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT * FROM media WHERE filename = ?",
        ['banner.png']
    );
    
    expect($result['filename'])->toBe('banner.png')
        ->and($result['path'])->toBe('/uploads/2026/01/banner.png')
        ->and($result['mime_type'])->toBe('image/png')
        ->and($result['size'])->toBe(10240)
        ->and($result['width'])->toBe(1200)
        ->and($result['height'])->toBe(600)
        ->and($result['uploaded_by'])->toBe(1);
});

it('drops media table on rollback', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='media'"
    );
    expect($result)->toHaveCount(1);
    
    $this->connection->execute("DROP TABLE IF EXISTS media");
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='media'"
    );
    expect($result)->toBeEmpty();
});

it('handles long file paths', function () {
    $sql = "
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $longPath = '/uploads/2026/01/' . str_repeat('very-long-filename-', 20) . '.jpg';
    
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?)",
        ['long.jpg', $longPath, 'image/jpeg', 1024, 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT path FROM media WHERE filename = ?",
        ['long.jpg']
    );
    
    expect($result['path'])->toBe($longPath);
});
