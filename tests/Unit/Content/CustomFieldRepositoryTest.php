<?php

/**
 * Custom Field Repository Unit Tests
 * 
 * Tests for CustomFieldRepository including type validation,
 * CRUD operations, and type casting.
 */

declare(strict_types=1);

use ChimeraNoWP\Content\CustomFieldRepository;
use ChimeraNoWP\Database\Connection;
use PDO;

beforeEach(function () {
    // Create test database connection
    $this->connection = new Connection([
        'default' => 'testing',
        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            ]
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 100
        ]
    ]);
    
    // Create tables
    $this->connection->getPdo()->exec("
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
            published_at TIMESTAMP NULL
        )
    ");
    
    $this->connection->getPdo()->exec("
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key VARCHAR(255) NOT NULL,
            field_value TEXT,
            field_type VARCHAR(50) NOT NULL,
            UNIQUE(content_id, field_key),
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
        )
    ");
    
    // Insert test content
    $this->connection->getPdo()->exec("
        INSERT INTO contents (title, slug, content, type, status, author_id)
        VALUES ('Test Post', 'test-post', 'Content', 'post', 'published', 1)
    ");
    
    $this->contentId = (int) $this->connection->lastInsertId();
    
    // Create repository
    $this->repository = new CustomFieldRepository($this->connection);
});

it('sets and gets string field', function () {
    $this->repository->setField($this->contentId, 'subtitle', 'My Subtitle', 'string');
    
    $value = $this->repository->getField($this->contentId, 'subtitle');
    
    expect($value)->toBe('My Subtitle')
        ->and($value)->toBeString();
});

it('sets and gets number field', function () {
    $this->repository->setField($this->contentId, 'views', 42, 'number');
    
    $value = $this->repository->getField($this->contentId, 'views');
    
    expect($value)->toBe(42)
        ->and($value)->toBeInt();
});

it('sets and gets float number field', function () {
    $this->repository->setField($this->contentId, 'rating', 4.5, 'number');
    
    $value = $this->repository->getField($this->contentId, 'rating');
    
    expect($value)->toBe(4.5)
        ->and($value)->toBeFloat();
});

it('sets and gets boolean field', function () {
    $this->repository->setField($this->contentId, 'featured', true, 'boolean');
    
    $value = $this->repository->getField($this->contentId, 'featured');
    
    expect($value)->toBeTrue()
        ->and($value)->toBeBool();
});

it('sets and gets false boolean field', function () {
    $this->repository->setField($this->contentId, 'archived', false, 'boolean');
    
    $value = $this->repository->getField($this->contentId, 'archived');
    
    expect($value)->toBeFalse()
        ->and($value)->toBeBool();
});

it('sets and gets date field from DateTime', function () {
    $date = new DateTime('2026-02-13 10:30:00');
    $this->repository->setField($this->contentId, 'event_date', $date, 'date');
    
    $value = $this->repository->getField($this->contentId, 'event_date');
    
    expect($value)->toBeInstanceOf(DateTime::class)
        ->and($value->format('Y-m-d H:i:s'))->toBe('2026-02-13 10:30:00');
});

it('sets and gets date field from string', function () {
    $this->repository->setField($this->contentId, 'publish_date', '2026-03-15', 'date');
    
    $value = $this->repository->getField($this->contentId, 'publish_date');
    
    expect($value)->toBeInstanceOf(DateTime::class)
        ->and($value->format('Y-m-d'))->toBe('2026-03-15');
});

it('throws exception for invalid field type', function () {
    $this->repository->setField($this->contentId, 'test', 'value', 'invalid_type');
})->throws(\InvalidArgumentException::class, 'Invalid field type');

it('throws exception when string value does not match string type', function () {
    $this->repository->setField($this->contentId, 'test', 123, 'string');
})->throws(\InvalidArgumentException::class, 'must be a string');

it('throws exception when value does not match number type', function () {
    $this->repository->setField($this->contentId, 'test', 'not a number', 'number');
})->throws(\InvalidArgumentException::class, 'must be numeric');

it('throws exception when value does not match boolean type', function () {
    $this->repository->setField($this->contentId, 'test', 'not a boolean', 'boolean');
})->throws(\InvalidArgumentException::class, 'must be a boolean');

it('throws exception when value does not match date type', function () {
    $this->repository->setField($this->contentId, 'test', 'invalid date', 'date');
})->throws(\InvalidArgumentException::class, 'must be a DateTime');

it('updates existing field', function () {
    $this->repository->setField($this->contentId, 'counter', 1, 'number');
    $this->repository->setField($this->contentId, 'counter', 2, 'number');
    
    $value = $this->repository->getField($this->contentId, 'counter');
    
    expect($value)->toBe(2);
});

it('returns null for non-existent field', function () {
    $value = $this->repository->getField($this->contentId, 'non_existent');
    
    expect($value)->toBeNull();
});

it('gets all fields for content', function () {
    $this->repository->setField($this->contentId, 'subtitle', 'Subtitle', 'string');
    $this->repository->setField($this->contentId, 'views', 100, 'number');
    $this->repository->setField($this->contentId, 'featured', true, 'boolean');
    
    $fields = $this->repository->getFieldsForContent($this->contentId);
    
    expect($fields)->toHaveCount(3)
        ->and($fields['subtitle'])->toBe('Subtitle')
        ->and($fields['views'])->toBe(100)
        ->and($fields['featured'])->toBeTrue();
});

it('sets multiple fields at once', function () {
    $fields = [
        'subtitle' => ['value' => 'My Subtitle', 'type' => 'string'],
        'views' => ['value' => 50, 'type' => 'number'],
        'featured' => ['value' => true, 'type' => 'boolean']
    ];
    
    $this->repository->setFields($this->contentId, $fields);
    
    $allFields = $this->repository->getFieldsForContent($this->contentId);
    
    expect($allFields)->toHaveCount(3)
        ->and($allFields['subtitle'])->toBe('My Subtitle')
        ->and($allFields['views'])->toBe(50)
        ->and($allFields['featured'])->toBeTrue();
});

it('sets multiple fields with default string type', function () {
    $fields = [
        'author_name' => 'John Doe',
        'category' => 'Technology'
    ];
    
    $this->repository->setFields($this->contentId, $fields);
    
    $allFields = $this->repository->getFieldsForContent($this->contentId);
    
    expect($allFields)->toHaveCount(2)
        ->and($allFields['author_name'])->toBe('John Doe')
        ->and($allFields['category'])->toBe('Technology');
});

it('deletes a field', function () {
    $this->repository->setField($this->contentId, 'temp', 'value', 'string');
    
    $deleted = $this->repository->deleteField($this->contentId, 'temp');
    
    expect($deleted)->toBeTrue();
    
    $value = $this->repository->getField($this->contentId, 'temp');
    expect($value)->toBeNull();
});

it('returns false when deleting non-existent field', function () {
    $deleted = $this->repository->deleteField($this->contentId, 'non_existent');
    
    expect($deleted)->toBeFalse();
});

it('deletes all fields for content', function () {
    $this->repository->setField($this->contentId, 'field1', 'value1', 'string');
    $this->repository->setField($this->contentId, 'field2', 'value2', 'string');
    $this->repository->setField($this->contentId, 'field3', 'value3', 'string');
    
    $deleted = $this->repository->deleteAllFields($this->contentId);
    
    expect($deleted)->toBe(3);
    
    $fields = $this->repository->getFieldsForContent($this->contentId);
    expect($fields)->toBeEmpty();
});

it('handles numeric strings correctly for number type', function () {
    $this->repository->setField($this->contentId, 'price', '19.99', 'number');
    
    $value = $this->repository->getField($this->contentId, 'price');
    
    expect($value)->toBe(19.99)
        ->and($value)->toBeFloat();
});

it('isolates fields between different content items', function () {
    // Create second content item
    $this->connection->getPdo()->exec("
        INSERT INTO contents (title, slug, content, type, status, author_id)
        VALUES ('Second Post', 'second-post', 'Content', 'post', 'published', 1)
    ");
    $contentId2 = (int) $this->connection->lastInsertId();
    
    // Set fields for both
    $this->repository->setField($this->contentId, 'color', 'red', 'string');
    $this->repository->setField($contentId2, 'color', 'blue', 'string');
    
    // Verify isolation
    $value1 = $this->repository->getField($this->contentId, 'color');
    $value2 = $this->repository->getField($contentId2, 'color');
    
    expect($value1)->toBe('red')
        ->and($value2)->toBe('blue');
});
