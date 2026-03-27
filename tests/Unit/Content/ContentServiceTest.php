<?php

/**
 * Content Service Unit Tests
 * 
 * Tests for ContentService business logic including CRUD operations,
 * caching, hooks integration, and versioning.
 */

declare(strict_types=1);

use ChimeraNoWP\Content\ContentService;
use ChimeraNoWP\Content\ContentRepository;
use ChimeraNoWP\Content\CustomFieldRepository;
use ChimeraNoWP\Content\Content;
use ChimeraNoWP\Content\ContentType;
use ChimeraNoWP\Content\ContentStatus;
use ChimeraNoWP\Plugin\HookSystem;
use ChimeraNoWP\Cache\CacheManager;
use ChimeraNoWP\Cache\NullCacheAdapter;
use ChimeraNoWP\Database\Connection;

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
        CREATE TABLE content_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            content TEXT,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            author_id INTEGER NOT NULL,
            locale VARCHAR(10) DEFAULT 'en',
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE
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
    
    // Create dependencies
    $this->repository = new ContentRepository($this->connection);
    $this->hooks = new HookSystem();
    $this->cache = new CacheManager(new NullCacheAdapter());
    $this->customFieldRepository = new CustomFieldRepository($this->connection);
    
    // Create service
    $this->service = new ContentService(
        $this->repository,
        $this->hooks,
        $this->cache,
        $this->connection,
        $this->customFieldRepository
    );
});

it('creates content with valid data', function () {
    $data = [
        'title' => 'Test Post',
        'content' => 'This is test content',
        'type' => 'post',
        'author_id' => 1
    ];
    
    $content = $this->service->createContent($data);
    
    expect($content)->toBeInstanceOf(Content::class)
        ->and($content->id)->toBeGreaterThan(0)
        ->and($content->title)->toBe('Test Post')
        ->and($content->slug)->toBe('test-post')
        ->and($content->type)->toBe(ContentType::POST);
});

it('generates unique slug automatically', function () {
    $data = [
        'title' => 'My Test Post',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ];
    
    $content = $this->service->createContent($data);
    
    expect($content->slug)->toBe('my-test-post');
});

it('ensures slug uniqueness by appending number', function () {
    $data = [
        'title' => 'Duplicate Title',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ];
    
    $content1 = $this->service->createContent($data);
    $content2 = $this->service->createContent($data);
    $content3 = $this->service->createContent($data);
    
    expect($content1->slug)->toBe('duplicate-title')
        ->and($content2->slug)->toBe('duplicate-title-1')
        ->and($content3->slug)->toBe('duplicate-title-2');
});

it('throws exception when title is missing', function () {
    $data = [
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ];
    
    $this->service->createContent($data);
})->throws(\InvalidArgumentException::class, 'Title is required');

it('throws exception when type is missing', function () {
    $data = [
        'title' => 'Test',
        'content' => 'Content',
        'author_id' => 1
    ];
    
    $this->service->createContent($data);
})->throws(\InvalidArgumentException::class, 'Type is required');

it('throws exception when author_id is missing', function () {
    $data = [
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post'
    ];
    
    $this->service->createContent($data);
})->throws(\InvalidArgumentException::class, 'Author ID is required');

it('throws exception for invalid content type', function () {
    $data = [
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'invalid_type',
        'author_id' => 1
    ];
    
    $this->service->createContent($data);
})->throws(\InvalidArgumentException::class, 'Invalid content type');

it('retrieves content by ID', function () {
    $created = $this->service->createContent([
        'title' => 'Test Post',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $retrieved = $this->service->getContent($created->id);
    
    expect($retrieved)->toBeInstanceOf(Content::class)
        ->and($retrieved->id)->toBe($created->id)
        ->and($retrieved->title)->toBe('Test Post');
});

it('retrieves content by slug', function () {
    $created = $this->service->createContent([
        'title' => 'Test Post',
        'slug' => 'custom-slug',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $retrieved = $this->service->getContentBySlug('custom-slug');
    
    expect($retrieved)->toBeInstanceOf(Content::class)
        ->and($retrieved->slug)->toBe('custom-slug');
});

it('returns null for non-existent content', function () {
    $content = $this->service->getContent(999);
    
    expect($content)->toBeNull();
});

it('updates content successfully', function () {
    $created = $this->service->createContent([
        'title' => 'Original Title',
        'content' => 'Original content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $updated = $this->service->updateContent($created->id, [
        'title' => 'Updated Title',
        'content' => 'Updated content'
    ]);
    
    expect($updated->title)->toBe('Updated Title')
        ->and($updated->content)->toBe('Updated content')
        ->and($updated->id)->toBe($created->id);
});

it('ensures slug uniqueness when updating', function () {
    $content1 = $this->service->createContent([
        'title' => 'First Post',
        'slug' => 'first-post',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $content2 = $this->service->createContent([
        'title' => 'Second Post',
        'slug' => 'second-post',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    // Try to update content2 to use content1's slug
    $updated = $this->service->updateContent($content2->id, [
        'slug' => 'first-post'
    ]);
    
    // Should get unique slug
    expect($updated->slug)->toBe('first-post-1');
});

it('deletes content successfully', function () {
    $created = $this->service->createContent([
        'title' => 'To Delete',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $deleted = $this->service->deleteContent($created->id);
    
    expect($deleted)->toBeTrue();
    
    $retrieved = $this->service->getContent($created->id);
    expect($retrieved)->toBeNull();
});

it('returns false when deleting non-existent content', function () {
    $deleted = $this->service->deleteContent(999);
    
    expect($deleted)->toBeFalse();
});

it('creates version when content is created', function () {
    $content = $this->service->createContent([
        'title' => 'Versioned Post',
        'content' => 'Original content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $versions = $this->service->getVersionHistory($content->id);
    
    expect($versions)->toHaveCount(1)
        ->and($versions[0]['title'])->toBe('Versioned Post')
        ->and($versions[0]['content'])->toBe('Original content');
});

it('creates new version when content is updated', function () {
    $content = $this->service->createContent([
        'title' => 'Original',
        'content' => 'Version 1',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $this->service->updateContent($content->id, [
        'content' => 'Version 2'
    ]);
    
    $this->service->updateContent($content->id, [
        'content' => 'Version 3'
    ]);
    
    $versions = $this->service->getVersionHistory($content->id);
    
    expect($versions)->toHaveCount(3);
});

it('fires content.created hook', function () {
    $hookFired = false;
    $capturedContent = null;
    
    $this->hooks->addAction('content.created', function ($content) use (&$hookFired, &$capturedContent) {
        $hookFired = true;
        $capturedContent = $content;
    });
    
    $content = $this->service->createContent([
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    expect($hookFired)->toBeTrue()
        ->and($capturedContent)->toBeInstanceOf(Content::class)
        ->and($capturedContent->id)->toBe($content->id);
});

it('fires content.updated hook', function () {
    $hookFired = false;
    
    $this->hooks->addAction('content.updated', function () use (&$hookFired) {
        $hookFired = true;
    });
    
    $content = $this->service->createContent([
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $this->service->updateContent($content->id, ['title' => 'Updated']);
    
    expect($hookFired)->toBeTrue();
});

it('fires content.deleted hook', function () {
    $hookFired = false;
    
    $this->hooks->addAction('content.deleted', function () use (&$hookFired) {
        $hookFired = true;
    });
    
    $content = $this->service->createContent([
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $this->service->deleteContent($content->id);
    
    expect($hookFired)->toBeTrue();
});

it('applies content.get filter hook', function () {
    $this->hooks->addFilter('content.get', function ($content) {
        $content->title = 'Modified: ' . $content->title;
        return $content;
    });
    
    $created = $this->service->createContent([
        'title' => 'Original',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $retrieved = $this->service->getContent($created->id);
    
    expect($retrieved->title)->toBe('Modified: Original');
});

it('retrieves all content with filters', function () {
    $this->service->createContent([
        'title' => 'Post 1',
        'content' => 'Content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1
    ]);
    
    $this->service->createContent([
        'title' => 'Post 2',
        'content' => 'Content',
        'type' => 'post',
        'status' => 'draft',
        'author_id' => 1
    ]);
    
    $this->service->createContent([
        'title' => 'Page 1',
        'content' => 'Content',
        'type' => 'page',
        'status' => 'published',
        'author_id' => 2
    ]);
    
    $allContent = $this->service->getAllContent();
    expect($allContent)->toHaveCount(3);
    
    $posts = $this->service->getAllContent(['type' => 'post']);
    expect($posts)->toHaveCount(2);
    
    $published = $this->service->getAllContent(['status' => 'published']);
    expect($published)->toHaveCount(2);
    
    $author1 = $this->service->getAllContent(['author_id' => 1]);
    expect($author1)->toHaveCount(2);
});

it('creates content with custom fields', function () {
    $data = [
        'title' => 'Post with Custom Fields',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1,
        'custom_fields' => [
            'subtitle' => ['value' => 'My Subtitle', 'type' => 'string'],
            'views' => ['value' => 100, 'type' => 'number'],
            'featured' => ['value' => true, 'type' => 'boolean']
        ]
    ];
    
    $content = $this->service->createContent($data);
    
    expect($content)->toBeInstanceOf(Content::class);
    
    // Verify custom fields were saved
    $fields = $this->customFieldRepository->getFieldsForContent($content->id);
    expect($fields)->toHaveCount(3)
        ->and($fields['subtitle'])->toBe('My Subtitle')
        ->and($fields['views'])->toBe(100)
        ->and($fields['featured'])->toBeTrue();
});

it('updates content with custom fields', function () {
    $content = $this->service->createContent([
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1,
        'custom_fields' => [
            'views' => ['value' => 50, 'type' => 'number']
        ]
    ]);
    
    $this->service->updateContent($content->id, [
        'custom_fields' => [
            'views' => ['value' => 100, 'type' => 'number'],
            'featured' => ['value' => true, 'type' => 'boolean']
        ]
    ]);
    
    $fields = $this->customFieldRepository->getFieldsForContent($content->id);
    expect($fields['views'])->toBe(100)
        ->and($fields['featured'])->toBeTrue();
});

it('deletes custom fields when content is deleted', function () {
    $content = $this->service->createContent([
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1,
        'custom_fields' => [
            'test_field' => ['value' => 'test', 'type' => 'string']
        ]
    ]);
    
    $this->service->deleteContent($content->id);
    
    $fields = $this->customFieldRepository->getFieldsForContent($content->id);
    expect($fields)->toBeEmpty();
});
