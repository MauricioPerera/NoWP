<?php

use ChimeraNoWP\Content\ContentRepository;
use ChimeraNoWP\Content\Content;
use ChimeraNoWP\Content\ContentType;
use ChimeraNoWP\Content\ContentStatus;
use ChimeraNoWP\Database\Connection;
use ChimeraNoWP\Database\QueryBuilder;

beforeEach(function () {
    // Use SQLite in-memory database for tests
    $config = [
        'default' => 'sqlite',
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'charset' => 'utf8',
                'collation' => '',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ],
        'retry' => [
            'attempts' => 3,
            'delay' => 10,
        ],
    ];
    
    $this->connection = new Connection($config);
    $this->repository = new ContentRepository($this->connection);
    
    // Create contents table
    $this->connection->execute('
        CREATE TABLE contents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            content TEXT,
            type TEXT NOT NULL,
            status TEXT NOT NULL,
            author_id INTEGER NOT NULL,
            parent_id INTEGER,
            locale VARCHAR(10) DEFAULT NULL,
            translation_group VARCHAR(50) DEFAULT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            published_at TEXT
        )
    ');
    
    // Start transaction for test isolation
    $this->connection->beginTransaction();
});

afterEach(function () {
    $this->connection->rollback();
});

describe('ContentRepository', function () {
    
    it('creates content and returns it with an ID', function () {
        $data = [
            'title' => 'Test Post',
            'slug' => 'test-post',
            'content' => 'This is test content',
            'type' => ContentType::POST->value,
            'status' => ContentStatus::DRAFT->value,
            'author_id' => 1,
        ];
        
        $content = $this->repository->create($data);
        
        expect($content)->toBeInstanceOf(Content::class)
            ->and($content->id)->toBeGreaterThan(0)
            ->and($content->title)->toBe('Test Post')
            ->and($content->slug)->toBe('test-post')
            ->and($content->type)->toBe(ContentType::POST)
            ->and($content->status)->toBe(ContentStatus::DRAFT);
    });
    
    it('finds content by ID', function () {
        $created = $this->repository->create([
            'title' => 'Find Me',
            'slug' => 'find-me',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $found = $this->repository->find($created->id);
        
        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($created->id)
            ->and($found->title)->toBe('Find Me');
    });
    
    it('returns null when content not found by ID', function () {
        $found = $this->repository->find(99999);
        
        expect($found)->toBeNull();
    });
    
    it('finds content by slug', function () {
        $this->repository->create([
            'title' => 'Slug Test',
            'slug' => 'unique-slug',
            'type' => ContentType::PAGE->value,
            'author_id' => 1,
        ]);
        
        $found = $this->repository->findBySlug('unique-slug');
        
        expect($found)->not->toBeNull()
            ->and($found->slug)->toBe('unique-slug')
            ->and($found->title)->toBe('Slug Test');
    });
    
    it('returns null when content not found by slug', function () {
        $found = $this->repository->findBySlug('non-existent-slug');
        
        expect($found)->toBeNull();
    });
    
    it('finds all content without filters', function () {
        $this->repository->create([
            'title' => 'Post 1',
            'slug' => 'post-1',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $this->repository->create([
            'title' => 'Post 2',
            'slug' => 'post-2',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $all = $this->repository->findAll();
        
        expect($all)->toBeArray()
            ->and(count($all))->toBeGreaterThanOrEqual(2);
    });
    
    it('filters content by type', function () {
        $this->repository->create([
            'title' => 'A Post',
            'slug' => 'a-post',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $this->repository->create([
            'title' => 'A Page',
            'slug' => 'a-page',
            'type' => ContentType::PAGE->value,
            'author_id' => 1,
        ]);
        
        $posts = $this->repository->findAll(['type' => ContentType::POST->value]);
        
        expect($posts)->toBeArray();
        foreach ($posts as $post) {
            expect($post->type)->toBe(ContentType::POST);
        }
    });
    
    it('filters content by status', function () {
        $this->repository->create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'type' => ContentType::POST->value,
            'status' => ContentStatus::DRAFT->value,
            'author_id' => 1,
        ]);
        
        $this->repository->create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'type' => ContentType::POST->value,
            'status' => ContentStatus::PUBLISHED->value,
            'author_id' => 1,
        ]);
        
        $published = $this->repository->findAll(['status' => ContentStatus::PUBLISHED->value]);
        
        expect($published)->toBeArray();
        foreach ($published as $content) {
            expect($content->status)->toBe(ContentStatus::PUBLISHED);
        }
    });
    
    it('filters content by author', function () {
        $this->repository->create([
            'title' => 'Author 1 Post',
            'slug' => 'author-1-post',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $this->repository->create([
            'title' => 'Author 2 Post',
            'slug' => 'author-2-post',
            'type' => ContentType::POST->value,
            'author_id' => 2,
        ]);
        
        $author1Content = $this->repository->findAll(['author_id' => 1]);
        
        expect($author1Content)->toBeArray();
        foreach ($author1Content as $content) {
            expect($content->authorId)->toBe(1);
        }
    });
    
    it('supports pagination with limit', function () {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create([
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'type' => ContentType::POST->value,
                'author_id' => 1,
            ]);
        }
        
        $limited = $this->repository->findAll(['limit' => 2]);
        
        expect($limited)->toHaveCount(2);
    });
    
    it('supports pagination with offset', function () {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $content = $this->repository->create([
                'title' => "Post {$i}",
                'slug' => "post-{$i}",
                'type' => ContentType::POST->value,
                'author_id' => 1,
            ]);
            $ids[] = $content->id;
        }
        
        $page1 = $this->repository->findAll(['limit' => 2, 'offset' => 0, 'order_by' => 'id', 'order_direction' => 'asc']);
        $page2 = $this->repository->findAll(['limit' => 2, 'offset' => 2, 'order_by' => 'id', 'order_direction' => 'asc']);
        
        expect($page1)->toHaveCount(2)
            ->and($page2)->toHaveCount(2)
            ->and($page1[0]->id)->not->toBe($page2[0]->id);
    });
    
    it('updates content', function () {
        $content = $this->repository->create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $updated = $this->repository->update($content->id, [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);
        
        expect($updated->id)->toBe($content->id)
            ->and($updated->title)->toBe('Updated Title')
            ->and($updated->content)->toBe('Updated content')
            ->and($updated->slug)->toBe('original-slug'); // Unchanged
    });
    
    it('deletes content', function () {
        $content = $this->repository->create([
            'title' => 'To Delete',
            'slug' => 'to-delete',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $deleted = $this->repository->delete($content->id);
        
        expect($deleted)->toBeTrue();
        
        $found = $this->repository->find($content->id);
        expect($found)->toBeNull();
    });
    
    it('returns false when deleting non-existent content', function () {
        $deleted = $this->repository->delete(99999);
        
        expect($deleted)->toBeFalse();
    });
    
    it('counts all content', function () {
        $this->repository->create([
            'title' => 'Count 1',
            'slug' => 'count-1',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $this->repository->create([
            'title' => 'Count 2',
            'slug' => 'count-2',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $count = $this->repository->count();
        
        expect($count)->toBeGreaterThanOrEqual(2);
    });
    
    it('counts content with filters', function () {
        $post = $this->repository->create([
            'title' => 'Post',
            'slug' => 'post-count',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $page = $this->repository->create([
            'title' => 'Page',
            'slug' => 'page-count',
            'type' => ContentType::PAGE->value,
            'author_id' => 1,
        ]);
        
        // Verify they were created
        expect($post->type)->toBe(ContentType::POST)
            ->and($page->type)->toBe(ContentType::PAGE);
        
        $postCount = $this->repository->count(['type' => ContentType::POST->value]);
        $pageCount = $this->repository->count(['type' => ContentType::PAGE->value]);
        
        expect($postCount)->toBeGreaterThanOrEqual(1)
            ->and($pageCount)->toBeGreaterThanOrEqual(1);
    });
    
    it('supports custom ordering', function () {
        $this->repository->create([
            'title' => 'Z Post',
            'slug' => 'z-post',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $this->repository->create([
            'title' => 'A Post',
            'slug' => 'a-post',
            'type' => ContentType::POST->value,
            'author_id' => 1,
        ]);
        
        $ascending = $this->repository->findAll([
            'order_by' => 'title',
            'order_direction' => 'asc',
            'limit' => 2
        ]);
        
        expect($ascending[0]->title)->toBeLessThan($ascending[1]->title);
    });
});
