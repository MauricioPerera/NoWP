<?php

/**
 * Content Controller Unit Tests
 * 
 * Tests for ContentController REST API endpoints.
 */

declare(strict_types=1);

use ChimeraNoWP\Content\ContentController;
use ChimeraNoWP\Content\ContentService;
use ChimeraNoWP\Content\ContentRepository;
use ChimeraNoWP\Content\CustomFieldRepository;
use ChimeraNoWP\Content\Content;
use ChimeraNoWP\Content\ContentType;
use ChimeraNoWP\Content\ContentStatus;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Plugin\HookSystem;
use ChimeraNoWP\Cache\CacheManager;
use ChimeraNoWP\Cache\NullCacheAdapter;
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
    $customFieldRepository = new CustomFieldRepository($this->connection);
    $repository = new ContentRepository($this->connection, $customFieldRepository);
    $hooks = new HookSystem();
    $cache = new CacheManager(new NullCacheAdapter());
    
    $this->service = new ContentService(
        $repository,
        $hooks,
        $cache,
        $this->connection,
        $customFieldRepository
    );
    
    // Create controller
    $this->controller = new ContentController($this->service);
});

it('lists all content', function () {
    // Create test content
    $this->service->createContent([
        'title' => 'Post 1',
        'content' => 'Content 1',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $this->service->createContent([
        'title' => 'Post 2',
        'content' => 'Content 2',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $request = new Request('GET', '/api/contents');
    $response = $this->controller->index($request);
    
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['data'])->toHaveCount(2);
});

it('filters content by type', function () {
    $this->service->createContent([
        'title' => 'Post 1',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $this->service->createContent([
        'title' => 'Page 1',
        'content' => 'Content',
        'type' => 'page',
        'author_id' => 1
    ]);
    
    $request = new Request('GET', '/api/contents', [], ['type' => 'post']);
    $response = $this->controller->index($request);
    
    $data = json_decode($response->getContent(), true);
    expect($data['data'])->toHaveCount(1)
        ->and($data['data'][0]['type'])->toBe('post');
});

it('shows single content by ID', function () {
    $content = $this->service->createContent([
        'title' => 'Test Post',
        'content' => 'Test content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $request = new Request('GET', "/api/contents/{$content->id}");
    $response = $this->controller->show($request, $content->id);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['data']['id'])->toBe($content->id)
        ->and($data['data']['title'])->toBe('Test Post');
});

it('returns 404 for non-existent content', function () {
    $request = new Request('GET', '/api/contents/999');
    $response = $this->controller->show($request, 999);
    
    expect($response->getStatusCode())->toBe(404);
    
    $data = json_decode($response->getContent(), true);
    expect($data['error']['code'])->toBe('CONTENT_NOT_FOUND');
});

it('creates new content', function () {
    $request = new Request('POST', '/api/contents', [], [], [
        'title' => 'New Post',
        'content' => 'New content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $response = $this->controller->store($request);
    
    expect($response->getStatusCode())->toBe(201);
    
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['data']['title'])->toBe('New Post')
        ->and($data['data']['id'])->toBeGreaterThan(0);
});

it('validates required fields when creating content', function () {
    $request = new Request('POST', '/api/contents', [], [], [
        'content' => 'Content without title'
    ]);
    
    $response = $this->controller->store($request);
    
    expect($response->getStatusCode())->toBe(400);
    
    $data = json_decode($response->getContent(), true);
    expect($data['error']['code'])->toBe('VALIDATION_ERROR');
});

it('uses authenticated user as author if not provided', function () {
    $request = new Request('POST', '/api/contents', [], [], [
        'title' => 'New Post',
        'content' => 'Content',
        'type' => 'post'
    ]);
    
    // Set authenticated user as User object
    $request->setAttribute('user', new \ChimeraNoWP\Auth\User(
        id: 42,
        email: 'test@example.com',
        passwordHash: '',
        displayName: 'Test User',
        role: \ChimeraNoWP\Auth\UserRole::EDITOR
    ));
    
    $response = $this->controller->store($request);
    
    expect($response->getStatusCode())->toBe(201);
    
    $data = json_decode($response->getContent(), true);
    expect($data['data']['author_id'])->toBe(42);
});

it('updates existing content', function () {
    $content = $this->service->createContent([
        'title' => 'Original Title',
        'content' => 'Original content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $request = new Request('PUT', "/api/contents/{$content->id}", [], [], [
        'title' => 'Updated Title',
        'content' => 'Updated content'
    ]);
    
    $response = $this->controller->update($request, $content->id);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue()
        ->and($data['data']['title'])->toBe('Updated Title')
        ->and($data['data']['content'])->toBe('Updated content');
});

it('returns 404 when updating non-existent content', function () {
    $request = new Request('PUT', '/api/contents/999', [], [], [
        'title' => 'Updated'
    ]);
    
    $response = $this->controller->update($request, 999);
    
    expect($response->getStatusCode())->toBe(404);
});

it('validates update data is not empty', function () {
    $content = $this->service->createContent([
        'title' => 'Test',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $request = new Request('PUT', "/api/contents/{$content->id}", [], [], []);
    
    $response = $this->controller->update($request, $content->id);
    
    expect($response->getStatusCode())->toBe(400);
});

it('deletes content', function () {
    $content = $this->service->createContent([
        'title' => 'To Delete',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $request = new Request('DELETE', "/api/contents/{$content->id}");
    $response = $this->controller->destroy($request, $content->id);
    
    expect($response->getStatusCode())->toBe(204);
    
    // Verify it's deleted
    $getResponse = $this->controller->show($request, $content->id);
    expect($getResponse->getStatusCode())->toBe(404);
});

it('returns 404 when deleting non-existent content', function () {
    $request = new Request('DELETE', '/api/contents/999');
    $response = $this->controller->destroy($request, 999);
    
    expect($response->getStatusCode())->toBe(404);
});

it('shows content by slug', function () {
    $this->service->createContent([
        'title' => 'Test Post',
        'slug' => 'test-post',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $request = new Request('GET', '/api/contents/slug/test-post');
    $response = $this->controller->showBySlug($request, 'test-post');
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['data']['slug'])->toBe('test-post');
});

it('returns 404 for non-existent slug', function () {
    $request = new Request('GET', '/api/contents/slug/non-existent');
    $response = $this->controller->showBySlug($request, 'non-existent');
    
    expect($response->getStatusCode())->toBe(404);
});

it('retrieves version history', function () {
    $content = $this->service->createContent([
        'title' => 'Original',
        'content' => 'Version 1',
        'type' => 'post',
        'author_id' => 1
    ]);
    
    $this->service->updateContent($content->id, ['content' => 'Version 2']);
    $this->service->updateContent($content->id, ['content' => 'Version 3']);
    
    $request = new Request('GET', "/api/contents/{$content->id}/versions");
    $response = $this->controller->versions($request, $content->id);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['data'])->toHaveCount(3);
});

it('creates content with custom fields', function () {
    $request = new Request('POST', '/api/contents', [], [], [
        'title' => 'Post with Fields',
        'content' => 'Content',
        'type' => 'post',
        'author_id' => 1,
        'custom_fields' => [
            'subtitle' => ['value' => 'My Subtitle', 'type' => 'string'],
            'views' => ['value' => 100, 'type' => 'number']
        ]
    ]);
    
    $response = $this->controller->store($request);
    
    expect($response->getStatusCode())->toBe(201);
    
    $data = json_decode($response->getContent(), true);
    
    // The content is created but we need to fetch it again to get custom fields loaded
    // because ContentService.createContent returns Content without reloading from repository
    $content = $this->service->getContent($data['data']['id']);
    
    expect($content->customFields)->toHaveKey('subtitle')
        ->and($content->customFields['subtitle'])->toBe('My Subtitle')
        ->and($content->customFields['views'])->toBe(100);
});

it('returns JSON error responses with proper structure', function () {
    $request = new Request('GET', '/api/contents/999');
    $response = $this->controller->show($request, 999);
    
    $data = json_decode($response->getContent(), true);
    
    expect($data)->toHaveKey('error')
        ->and($data['error'])->toHaveKey('code')
        ->and($data['error'])->toHaveKey('message');
});
