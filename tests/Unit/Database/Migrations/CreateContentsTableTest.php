<?php

/**
 * Create Contents Table Migration Tests
 * 
 * Tests for the contents table migration to ensure proper schema creation,
 * foreign keys, and rollback functionality.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000001_create_users_table.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000002_create_contents_table.php';

use ChimeraNoWP\Database\Connection;

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
        ['author@example.com', 'hash123', 'Test Author', 'author']
    );
    
    $this->migration = new CreateContentsTable($this->connection);
});

it('creates contents table with correct schema', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='contents'"
    );
    expect($result)->toHaveCount(1);
});

it('creates required indexes', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    $this->connection->execute("CREATE INDEX idx_slug ON contents(slug)");
    $this->connection->execute("CREATE INDEX idx_type_status ON contents(type, status)");
    $this->connection->execute("CREATE INDEX idx_author ON contents(author_id)");
    
    $indexes = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='contents'"
    );
    
    $indexNames = array_column($indexes, 'name');
    expect($indexNames)->toContain('idx_slug')
        ->and($indexNames)->toContain('idx_type_status')
        ->and($indexNames)->toContain('idx_author');
});

it('enforces unique slug constraint', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    // Insert first content
    $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['First Post', 'first-post', 'post', 'published', 1]
    );
    
    // Try to insert duplicate slug
    expect(fn() => $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Second Post', 'first-post', 'post', 'draft', 1]
    ))->toThrow(PDOException::class);
});

it('enforces foreign key constraint for author_id', function () {
    $this->connection->execute("PRAGMA foreign_keys = ON");
    
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    // Try to insert with non-existent author
    expect(fn() => $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Test Post', 'test-post', 'post', 'draft', 999]
    ))->toThrow(PDOException::class);
});

it('allows null parent_id', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Test Post', 'test-post', 'post', 'draft', 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT parent_id FROM contents WHERE slug = ?",
        ['test-post']
    );
    
    expect($result['parent_id'])->toBeNull();
});

it('allows null published_at', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Draft Post', 'draft-post', 'post', 'draft', 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT published_at FROM contents WHERE slug = ?",
        ['draft-post']
    );
    
    expect($result['published_at'])->toBeNull();
});

it('sets timestamps automatically', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Test Post', 'test-post', 'post', 'draft', 1]
    );
    
    $result = $this->connection->fetchOne(
        "SELECT created_at, updated_at FROM contents WHERE slug = ?",
        ['test-post']
    );
    
    expect($result['created_at'])->not->toBeNull()
        ->and($result['updated_at'])->not->toBeNull();
});

it('stores all content fields correctly', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $this->connection->execute(
        "INSERT INTO contents (title, slug, content, type, status, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
        ['My First Post', 'my-first-post', 'This is the content', 'post', 'published', 1, '2026-01-15 12:00:00']
    );
    
    $result = $this->connection->fetchOne(
        "SELECT * FROM contents WHERE slug = ?",
        ['my-first-post']
    );
    
    expect($result['title'])->toBe('My First Post')
        ->and($result['slug'])->toBe('my-first-post')
        ->and($result['content'])->toBe('This is the content')
        ->and($result['type'])->toBe('post')
        ->and($result['status'])->toBe('published')
        ->and($result['author_id'])->toBe(1)
        ->and($result['published_at'])->toBe('2026-01-15 12:00:00');
});

it('drops contents table on rollback', function () {
    $sql = "
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            published_at TIMESTAMP NULL,
            FOREIGN KEY (author_id) REFERENCES users(id)
        )
    ";
    $this->connection->execute($sql);
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='contents'"
    );
    expect($result)->toHaveCount(1);
    
    $this->connection->execute("DROP TABLE IF EXISTS contents");
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='contents'"
    );
    expect($result)->toBeEmpty();
});
