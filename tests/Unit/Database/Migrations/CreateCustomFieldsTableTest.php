<?php

/**
 * Create Custom Fields Table Migration Tests
 * 
 * Tests for the custom_fields table migration to ensure proper schema creation,
 * foreign keys with cascade delete, and rollback functionality.
 * 
 * Requirements: 2.1, 2.5
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000001_create_users_table.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000002_create_contents_table.php';
require_once __DIR__ . '/../../../../migrations/2026_01_15_000004_create_custom_fields_table.php';

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
    
    // Create users table
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
    
    // Create contents table
    $this->connection->execute("
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
    ");

    // Insert test content
    $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Test Post', 'test-post', 'post', 'draft', 1]
    );
    
    $this->migration = new CreateCustomFieldsTable($this->connection);
});

it('creates custom_fields table with correct schema', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='custom_fields'"
    );
    expect($result)->toHaveCount(1);
});

it('enforces unique constraint on content_id and field_key', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Insert first custom field
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'author_bio', 'A short bio', 'string']
    );
    
    // Try to insert duplicate field_key for same content
    expect(fn() => $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'author_bio', 'Another bio', 'string']
    ))->toThrow(PDOException::class);
});

it('allows same field_key for different contents', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Insert second content
    $this->connection->execute(
        "INSERT INTO contents (title, slug, type, status, author_id) VALUES (?, ?, ?, ?, ?)",
        ['Second Post', 'second-post', 'post', 'draft', 1]
    );
    
    // Insert same field_key for different contents
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'featured', 'true', 'boolean']
    );
    
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [2, 'featured', 'false', 'boolean']
    );
    
    $result = $this->connection->fetchAll(
        "SELECT * FROM custom_fields WHERE field_key = ?",
        ['featured']
    );
    
    expect($result)->toHaveCount(2);
});

it('enforces foreign key constraint for content_id', function () {
    $this->connection->execute("PRAGMA foreign_keys = ON");
    
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Try to insert with non-existent content
    expect(fn() => $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [999, 'test_field', 'test value', 'string']
    ))->toThrow(PDOException::class);
});

it('cascades delete when content is deleted', function () {
    $this->connection->execute("PRAGMA foreign_keys = ON");
    
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Insert custom fields
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'field1', 'value1', 'string']
    );
    
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'field2', 'value2', 'string']
    );
    
    // Verify fields exist
    $result = $this->connection->fetchAll(
        "SELECT * FROM custom_fields WHERE content_id = ?",
        [1]
    );
    expect($result)->toHaveCount(2);
    
    // Delete content
    $this->connection->execute("DELETE FROM contents WHERE id = ?", [1]);
    
    // Verify custom fields were deleted
    $result = $this->connection->fetchAll(
        "SELECT * FROM custom_fields WHERE content_id = ?",
        [1]
    );
    expect($result)->toBeEmpty();
});

it('stores different field types', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Insert different field types
    $fields = [
        ['content_id' => 1, 'field_key' => 'title', 'field_value' => 'My Title', 'field_type' => 'string'],
        ['content_id' => 1, 'field_key' => 'views', 'field_value' => '1234', 'field_type' => 'number'],
        ['content_id' => 1, 'field_key' => 'published', 'field_value' => 'true', 'field_type' => 'boolean'],
        ['content_id' => 1, 'field_key' => 'publish_date', 'field_value' => '2026-01-15', 'field_type' => 'date'],
    ];
    
    foreach ($fields as $field) {
        $this->connection->execute(
            "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
            [$field['content_id'], $field['field_key'], $field['field_value'], $field['field_type']]
        );
    }
    
    $result = $this->connection->fetchAll(
        "SELECT field_key, field_type FROM custom_fields WHERE content_id = ? ORDER BY field_key",
        [1]
    );
    
    expect($result)->toHaveCount(4)
        ->and($result[0]['field_type'])->toBe('date')
        ->and($result[1]['field_type'])->toBe('boolean')
        ->and($result[2]['field_type'])->toBe('string')
        ->and($result[3]['field_type'])->toBe('number');
});

it('allows null field_value', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Insert field with null value
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'optional_field', null, 'string']
    );
    
    $result = $this->connection->fetchOne(
        "SELECT field_value FROM custom_fields WHERE field_key = ?",
        ['optional_field']
    );
    
    expect($result['field_value'])->toBeNull();
});

it('stores long text values', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    $longText = str_repeat('Lorem ipsum dolor sit amet. ', 1000);
    
    $this->connection->execute(
        "INSERT INTO custom_fields (content_id, field_key, field_value, field_type) VALUES (?, ?, ?, ?)",
        [1, 'long_description', $longText, 'string']
    );
    
    $result = $this->connection->fetchOne(
        "SELECT field_value FROM custom_fields WHERE field_key = ?",
        ['long_description']
    );
    
    expect($result['field_value'])->toBe($longText);
});

it('requires all non-nullable fields', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    // Try to insert without required fields
    expect(fn() => $this->connection->execute(
        "INSERT INTO custom_fields (content_id) VALUES (?)",
        [1]
    ))->toThrow(PDOException::class);
});

it('drops custom_fields table on rollback', function () {
    $sql = "
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE (content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ";
    $this->connection->execute($sql);
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='custom_fields'"
    );
    expect($result)->toHaveCount(1);
    
    $this->connection->execute("DROP TABLE IF EXISTS custom_fields");
    
    $result = $this->connection->fetchAll(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='custom_fields'"
    );
    expect($result)->toBeEmpty();
});
