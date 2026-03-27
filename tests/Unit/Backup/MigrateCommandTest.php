<?php

use ChimeraNoWP\Backup\MigrateCommand;
use ChimeraNoWP\Database\Connection;

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
    
    // Create test tables
    $this->connection->execute("
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            content TEXT,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL
        )
    ");
    
    $this->connection->execute("
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY,
            content_id INTEGER,
            field_key TEXT,
            field_value TEXT
        )
    ");
    
    $this->connection->execute("
        CREATE TABLE media (
            id INTEGER PRIMARY KEY,
            filename TEXT,
            path TEXT
        )
    ");
    
    $this->command = new MigrateCommand($this->connection);
});

test('migrates URLs in content', function () {
    $this->connection->execute("
        INSERT INTO contents (id, title, content) 
        VALUES (1, 'Test', 'Visit https://old-site.com/page for more info')
    ");
    
    $stats = $this->command->execute('https://old-site.com', 'https://new-site.com');
    
    expect($stats['contents_updated'])->toBeGreaterThan(0);
    
    $content = $this->connection->fetchOne("SELECT content FROM contents WHERE id = 1");
    expect($content['content'])->toContain('https://new-site.com/page');
    expect($content['content'])->not->toContain('https://old-site.com');
});

test('migrates URLs in custom fields', function () {
    $this->connection->execute("
        INSERT INTO custom_fields (id, content_id, field_key, field_value) 
        VALUES (1, 1, 'link', 'https://old-site.com/resource')
    ");
    
    $stats = $this->command->execute('https://old-site.com', 'https://new-site.com');
    
    expect($stats['custom_fields_updated'])->toBeGreaterThan(0);
    
    $field = $this->connection->fetchOne("SELECT field_value FROM custom_fields WHERE id = 1");
    expect($field['field_value'])->toBe('https://new-site.com/resource');
});

test('migrates URLs in media paths', function () {
    $this->connection->execute("
        INSERT INTO media (id, filename, path) 
        VALUES (1, 'image.jpg', 'https://old-site.com/uploads/image.jpg')
    ");
    
    $stats = $this->command->execute('https://old-site.com', 'https://new-site.com');
    
    expect($stats['media_updated'])->toBeGreaterThan(0);
    
    $media = $this->connection->fetchOne("SELECT path FROM media WHERE id = 1");
    expect($media['path'])->toBe('https://new-site.com/uploads/image.jpg');
});

test('normalizes URLs by removing trailing slashes', function () {
    $this->connection->execute("
        INSERT INTO contents (id, title, content) 
        VALUES (1, 'Test', 'Link: https://old-site.com/page')
    ");
    
    // Pass URLs with trailing slashes
    $this->command->execute('https://old-site.com/', 'https://new-site.com/');
    
    $content = $this->connection->fetchOne("SELECT content FROM contents WHERE id = 1");
    expect($content['content'])->toContain('https://new-site.com/page');
});

test('returns statistics about migration', function () {
    $this->connection->execute("
        INSERT INTO contents (id, title, content) 
        VALUES (1, 'Test', 'https://old-site.com/page')
    ");
    
    $this->connection->execute("
        INSERT INTO custom_fields (id, content_id, field_key, field_value) 
        VALUES (1, 1, 'url', 'https://old-site.com/resource')
    ");
    
    $stats = $this->command->execute('https://old-site.com', 'https://new-site.com');
    
    expect($stats)->toHaveKeys(['contents_updated', 'custom_fields_updated', 'media_updated']);
    expect($stats['contents_updated'])->toBeInt();
    expect($stats['custom_fields_updated'])->toBeInt();
    expect($stats['media_updated'])->toBeInt();
});

test('rolls back on error', function () {
    $this->connection->execute("
        INSERT INTO contents (id, title, content) 
        VALUES (1, 'Test', 'https://old-site.com/page')
    ");
    
    // Drop custom_fields table to cause an error
    $this->connection->execute("DROP TABLE custom_fields");
    
    try {
        $this->command->execute('https://old-site.com', 'https://new-site.com');
        $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
        // Expected
    }
    
    // Verify rollback - content should still have old URL
    $content = $this->connection->fetchOne("SELECT content FROM contents WHERE id = 1");
    expect($content['content'])->toContain('https://old-site.com/page');
});

test('can skip specific tables', function () {
    $this->connection->execute("
        INSERT INTO contents (id, title, content) 
        VALUES (1, 'Test', 'https://old-site.com/page')
    ");
    
    $this->connection->execute("
        INSERT INTO custom_fields (id, content_id, field_key, field_value) 
        VALUES (1, 1, 'url', 'https://old-site.com/resource')
    ");
    
    $stats = $this->command->execute('https://old-site.com', 'https://new-site.com', [
        'update_contents' => true,
        'update_custom_fields' => false,
        'update_media' => false,
    ]);
    
    expect($stats['contents_updated'])->toBeGreaterThan(0);
    expect($stats['custom_fields_updated'])->toBe(0);
    expect($stats['media_updated'])->toBe(0);
    
    // Verify custom_fields still has old URL
    $field = $this->connection->fetchOne("SELECT field_value FROM custom_fields WHERE id = 1");
    expect($field['field_value'])->toBe('https://old-site.com/resource');
});
