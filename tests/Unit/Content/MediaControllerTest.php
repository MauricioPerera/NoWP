<?php

/**
 * Media Controller Unit Tests
 * 
 * Tests for MediaController REST API endpoints.
 */

declare(strict_types=1);

use ChimeraNoWP\Content\MediaController;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Storage\FileManager;
use ChimeraNoWP\Storage\ImageProcessor;
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
    
    // Create media table
    $this->connection->getPdo()->exec("
        CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size INTEGER NOT NULL,
            width INTEGER NULL,
            height INTEGER NULL,
            uploaded_by INTEGER NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create test upload directory
    $this->testUploadPath = __DIR__ . '/../../fixtures/media-uploads';
    if (!is_dir($this->testUploadPath)) {
        mkdir($this->testUploadPath, 0755, true);
    }
    
    // Create FileManager and ImageProcessor
    $this->fileManager = new FileManager($this->testUploadPath, '/uploads');
    $this->imageProcessor = new ImageProcessor();
    
    $this->controller = new MediaController(
        $this->fileManager,
        $this->imageProcessor,
        $this->connection
    );
});

afterEach(function () {
    // Clean up test files
    if (is_dir($this->testUploadPath)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testUploadPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($this->testUploadPath);
    }
});

it('returns error when no file is uploaded', function () {
    $_FILES = [];
    
    $request = new Request('POST', '/api/media', [], []);
    $response = $this->controller->upload($request);
    
    expect($response->getStatusCode())->toBe(400);
    
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error')
        ->and($data['error'])->toContain('No file uploaded');
});

it('lists media files with pagination', function () {
    // Insert test media
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        ['test1.jpg', '2026/02/test1.jpg', 'image/jpeg', 1024, 800, 600, 1, date('Y-m-d H:i:s')]
    );
    
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        ['test2.jpg', '2026/02/test2.jpg', 'image/jpeg', 2048, 1024, 768, 1, date('Y-m-d H:i:s')]
    );
    
    $request = new Request('GET', '/api/media', [], []);
    $response = $this->controller->index($request);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('data')
        ->and($data)->toHaveKey('page')
        ->and($data)->toHaveKey('per_page')
        ->and($data['data'])->toHaveCount(2);
});

it('filters media by MIME type', function () {
    // Insert test media with different types
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        ['test.jpg', '2026/02/test.jpg', 'image/jpeg', 1024, 800, 600, 1, date('Y-m-d H:i:s')]
    );
    
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        ['test.png', '2026/02/test.png', 'image/png', 2048, 1024, 768, 1, date('Y-m-d H:i:s')]
    );
    
    $request = new Request('GET', '/api/media?mime_type=image/jpeg', [], ['mime_type' => 'image/jpeg']);
    $response = $this->controller->index($request);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['data'])->toHaveCount(1)
        ->and($data['data'][0]['mime_type'])->toBe('image/jpeg');
});

it('shows a single media file', function () {
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        ['test.jpg', '2026/02/test.jpg', 'image/jpeg', 1024, 800, 600, 1, date('Y-m-d H:i:s')]
    );
    
    $mediaId = (int) $this->connection->lastInsertId();
    
    $response = $this->controller->show($mediaId);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('id')
        ->and($data['id'])->toBe($mediaId)
        ->and($data)->toHaveKey('filename')
        ->and($data['filename'])->toBe('test.jpg');
});

it('returns 404 when media not found', function () {
    $response = $this->controller->show(999);
    
    expect($response->getStatusCode())->toBe(404);
    
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error')
        ->and($data['error'])->toContain('not found');
});

it('deletes a media file', function () {
    // Create a test file
    $testFile = $this->testUploadPath . '/test.txt';
    file_put_contents($testFile, 'test content');
    
    $this->connection->execute(
        "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        ['test.txt', 'test.txt', 'text/plain', 12, null, null, 1, date('Y-m-d H:i:s')]
    );
    
    $mediaId = (int) $this->connection->lastInsertId();
    
    $response = $this->controller->destroy($mediaId);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('message')
        ->and($data['message'])->toContain('deleted successfully');
    
    // Verify file was deleted
    expect(file_exists($testFile))->toBeFalse();
    
    // Verify database record was deleted
    $queryBuilder = new ChimeraNoWP\Database\QueryBuilder($this->connection);
    $record = $queryBuilder->table('media')
        ->select(['*'])
        ->where('id', $mediaId)
        ->first();
    
    expect($record)->toBeNull();
});

it('returns 404 when deleting non-existent media', function () {
    $response = $this->controller->destroy(999);
    
    expect($response->getStatusCode())->toBe(404);
});

it('handles pagination parameters', function () {
    // Insert multiple media files
    for ($i = 1; $i <= 25; $i++) {
        $this->connection->execute(
            "INSERT INTO media (filename, path, mime_type, size, width, height, uploaded_by, uploaded_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            ["test{$i}.jpg", "2026/02/test{$i}.jpg", 'image/jpeg', 1024, 800, 600, 1, date('Y-m-d H:i:s')]
        );
    }
    
    // Request page 2 with 10 items per page
    $request = new Request('GET', '/api/media?page=2&per_page=10', [], ['page' => '2', 'per_page' => '10']);
    $response = $this->controller->index($request);
    
    expect($response->getStatusCode())->toBe(200);
    
    $data = json_decode($response->getContent(), true);
    expect($data['data'])->toHaveCount(10)
        ->and($data['page'])->toBe(2)
        ->and($data['per_page'])->toBe(10);
});
