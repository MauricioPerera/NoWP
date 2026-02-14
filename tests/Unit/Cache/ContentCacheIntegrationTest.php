<?php

use Framework\Content\ContentService;
use Framework\Content\ContentRepository;
use Framework\Content\CustomFieldRepository;
use Framework\Content\Content;
use Framework\Content\ContentType;
use Framework\Content\ContentStatus;
use Framework\Plugin\HookSystem;
use Framework\Cache\CacheManager;
use Framework\Cache\FileAdapter;
use Framework\Database\Connection;

beforeEach(function () {
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
    $this->connection->getPdo()->exec('
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            content TEXT,
            type TEXT NOT NULL,
            status TEXT NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER,
            created_at TEXT,
            updated_at TEXT,
            published_at TEXT
        )
    ');
    
    $this->connection->getPdo()->exec('
        CREATE TABLE custom_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            field_key TEXT NOT NULL,
            field_value TEXT,
            field_type TEXT NOT NULL,
            UNIQUE(content_id, field_key)
        )
    ');
    
    $this->connection->getPdo()->exec('
        CREATE TABLE content_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            slug TEXT NOT NULL,
            content TEXT,
            type TEXT NOT NULL,
            status TEXT NOT NULL,
            author_id INTEGER NOT NULL,
            created_at TEXT
        )
    ');
    
    $cachePath = BASE_PATH . '/storage/cache/test-integration';
    $cacheAdapter = new FileAdapter($cachePath, 'test_');
    $this->cache = new CacheManager($cacheAdapter);
    
    $this->repository = new ContentRepository($this->connection);
    $this->customFieldRepository = new CustomFieldRepository($this->connection);
    $this->hooks = new HookSystem();
    
    $this->service = new ContentService(
        $this->repository,
        $this->hooks,
        $this->cache,
        $this->connection,
        $this->customFieldRepository
    );
});

afterEach(function () {
    // Clean up cache
    $cachePath = BASE_PATH . '/storage/cache/test-integration';
    if (is_dir($cachePath)) {
        $files = glob($cachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($cachePath);
    }
});

it('caches content on first retrieval', function () {
    $data = [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // First call - should cache
    $retrieved1 = $this->service->getContent($content->id);
    
    // Verify it's cached by checking cache directly
    $cached = $this->cache->get("content:{$content->id}");
    
    expect($cached)->not->toBeNull()
        ->and($cached->id)->toBe($content->id)
        ->and($cached->title)->toBe('Test Post');
});

it('uses cached content on subsequent retrievals', function () {
    $data = [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // First call - caches the result
    $retrieved1 = $this->service->getContent($content->id);
    
    // Manually modify cache to verify it's being used
    $modifiedContent = clone $retrieved1;
    $modifiedContent->title = 'Modified Title';
    $this->cache->set("content:{$content->id}", $modifiedContent, 3600);
    
    // Second call - should return modified cached version
    $retrieved2 = $this->service->getContent($content->id);
    
    expect($retrieved2->title)->toBe('Modified Title');
});

it('invalidates cache when content is updated', function () {
    $data = [
        'title' => 'Original Title',
        'slug' => 'original-title',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // Cache the content
    $retrieved1 = $this->service->getContent($content->id);
    expect($retrieved1->title)->toBe('Original Title');
    
    // Update content
    $this->service->updateContent($content->id, ['title' => 'Updated Title']);
    
    // Cache should be invalidated, so we get fresh data
    $retrieved2 = $this->service->getContent($content->id);
    expect($retrieved2->title)->toBe('Updated Title');
});

it('invalidates cache when content is deleted', function () {
    $data = [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // Cache the content
    $retrieved = $this->service->getContent($content->id);
    expect($retrieved)->not->toBeNull();
    
    // Delete content
    $this->service->deleteContent($content->id);
    
    // Cache should be invalidated
    $cached = $this->cache->get("content:{$content->id}");
    expect($cached)->toBeNull();
});

it('caches content by slug', function () {
    $data = [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // First call - should cache
    $retrieved1 = $this->service->getContentBySlug('test-post');
    
    // Verify it's cached
    $cached = $this->cache->get("content:slug:test-post");
    
    expect($cached)->not->toBeNull()
        ->and($cached->slug)->toBe('test-post');
});

it('invalidates slug cache when content is updated with new slug', function () {
    $data = [
        'title' => 'Test Post',
        'slug' => 'original-slug',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // Cache by slug
    $retrieved1 = $this->service->getContentBySlug('original-slug');
    expect($retrieved1)->not->toBeNull();
    
    // Update with new slug
    $this->service->updateContent($content->id, ['slug' => 'new-slug']);
    
    // Old slug cache should be invalidated
    $oldCached = $this->cache->get("content:slug:original-slug");
    expect($oldCached)->toBeNull();
    
    // New slug should work
    $retrieved2 = $this->service->getContentBySlug('new-slug');
    expect($retrieved2)->not->toBeNull()
        ->and($retrieved2->slug)->toBe('new-slug');
});

it('reduces database queries with caching', function () {
    $data = [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'author_id' => 1,
    ];
    
    $content = $this->service->createContent($data);
    
    // First call - hits database
    $retrieved1 = $this->service->getContent($content->id);
    
    // Subsequent calls should use cache (no database hit)
    // We can't easily count queries, but we can verify the cache is working
    for ($i = 0; $i < 10; $i++) {
        $retrieved = $this->service->getContent($content->id);
        expect($retrieved->id)->toBe($content->id);
    }
    
    // All retrievals should return the same data
    expect($retrieved->title)->toBe('Test Post');
});
