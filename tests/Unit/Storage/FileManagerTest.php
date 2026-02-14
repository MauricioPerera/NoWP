<?php

/**
 * File Manager Unit Tests
 * 
 * Tests for FileManager including upload, delete, validation, and file organization.
 */

declare(strict_types=1);

use Framework\Storage\FileManager;
use Framework\Storage\StoredFile;

beforeEach(function () {
    // Create temporary upload directory for tests
    $this->testUploadPath = __DIR__ . '/../../fixtures/uploads';
    $this->testBaseUrl = '/uploads';
    
    // Clean up any existing test files
    if (is_dir($this->testUploadPath)) {
        cleanDirectory($this->testUploadPath);
    }
    
    if (!is_dir($this->testUploadPath)) {
        mkdir($this->testUploadPath, 0755, true);
    }
    
    $this->manager = new FileManager(
        $this->testUploadPath,
        $this->testBaseUrl
    );
});

afterEach(function () {
    // Clean up test files
    if (is_dir($this->testUploadPath)) {
        cleanDirectory($this->testUploadPath);
        rmdir($this->testUploadPath);
    }
});

// Helper function to clean directory recursively
function cleanDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            cleanDirectory($path);
            rmdir($path);
        } else {
            unlink($path);
        }
    }
}

// Helper function to create test file
function createTestFile(string $name = 'test.txt', string $content = 'test content'): string
{
    $tempFile = sys_get_temp_dir() . '/' . $name;
    file_put_contents($tempFile, $content);
    return $tempFile;
}

it('uploads a file successfully', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    expect($result)->toBeInstanceOf(StoredFile::class)
        ->and($result->filename)->toContain('test')
        ->and($result->filename)->toContain('.txt')
        ->and($result->size)->toBeGreaterThan(0)
        ->and($result->mimeType)->toBe('text/plain');
    
    unlink($sourceFile);
});

it('generates unique filenames for uploads', function () {
    $sourceFile1 = createTestFile('document.txt', 'content 1');
    $sourceFile2 = createTestFile('document.txt', 'content 2');
    
    $file1 = [
        'name' => 'document.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile1),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile1
    ];
    
    $file2 = [
        'name' => 'document.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile2),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile2
    ];
    
    $result1 = $this->manager->upload($file1);
    $result2 = $this->manager->upload($file2);
    
    expect($result1->filename)->not->toBe($result2->filename);
    
    if (file_exists($sourceFile1)) unlink($sourceFile1);
    if (file_exists($sourceFile2)) unlink($sourceFile2);
});

it('organizes files by year and month', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    $expectedPath = date('Y') . '/' . date('m');
    expect($result->path)->toContain($expectedPath);
    
    unlink($sourceFile);
});

it('validates file size', function () {
    $manager = new FileManager(
        $this->testUploadPath,
        $this->testBaseUrl,
        [],
        100 // 100 bytes max
    );
    
    $sourceFile = createTestFile('large.txt', str_repeat('x', 200));
    
    $file = [
        'name' => 'large.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    expect(fn() => $manager->upload($file))
        ->toThrow(RuntimeException::class, 'exceeds maximum allowed size');
    
    unlink($sourceFile);
});

it('validates MIME types', function () {
    $manager = new FileManager(
        $this->testUploadPath,
        $this->testBaseUrl,
        ['image/jpeg', 'image/png']
    );
    
    $sourceFile = createTestFile('document.txt');
    
    $file = [
        'name' => 'document.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    expect(fn() => $manager->upload($file))
        ->toThrow(RuntimeException::class, 'not allowed');
    
    unlink($sourceFile);
});

it('deletes a file', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    expect($this->manager->exists($result->path))->toBeTrue();
    
    $deleted = $this->manager->delete($result->path);
    
    expect($deleted)->toBeTrue()
        ->and($this->manager->exists($result->path))->toBeFalse();
    
    unlink($sourceFile);
});

it('returns false when deleting non-existent file', function () {
    $result = $this->manager->delete('non-existent/file.txt');
    
    expect($result)->toBeFalse();
});

it('checks if file exists', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    expect($this->manager->exists($result->path))->toBeTrue()
        ->and($this->manager->exists('non-existent.txt'))->toBeFalse();
    
    unlink($sourceFile);
});

it('generates public URL for file', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    expect($result->url)->toStartWith('/uploads/')
        ->and($result->url)->toContain($result->filename);
    
    unlink($sourceFile);
});

it('moves a file to new location', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    $originalPath = $result->path;
    $newPath = 'moved/' . $result->filename;
    
    $moved = $this->manager->move($originalPath, $newPath);
    
    expect($moved)->toBeTrue()
        ->and($this->manager->exists($originalPath))->toBeFalse()
        ->and($this->manager->exists($newPath))->toBeTrue();
    
    unlink($sourceFile);
});

it('returns false when moving non-existent file', function () {
    $result = $this->manager->move('non-existent.txt', 'destination.txt');
    
    expect($result)->toBeFalse();
});

it('sanitizes filenames', function () {
    $sourceFile = createTestFile('test file with spaces!@#.txt');
    
    $file = [
        'name' => 'test file with spaces!@#.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    // Filename should not contain special characters or spaces
    expect($result->filename)->not->toContain(' ')
        ->and($result->filename)->not->toContain('!')
        ->and($result->filename)->not->toContain('@')
        ->and($result->filename)->not->toContain('#');
    
    unlink($sourceFile);
});

it('handles upload errors', function () {
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => 100,
        'error' => UPLOAD_ERR_NO_FILE
    ];
    
    expect(fn() => $this->manager->upload($file))
        ->toThrow(RuntimeException::class, 'No file was uploaded');
});

it('creates directory structure automatically', function () {
    $sourceFile = createTestFile('test.txt');
    
    $file = [
        'name' => 'test.txt',
        'type' => 'text/plain',
        'size' => filesize($sourceFile),
        'error' => UPLOAD_ERR_OK,
        'source' => $sourceFile
    ];
    
    $result = $this->manager->upload($file);
    
    $datePath = date('Y') . '/' . date('m');
    $expectedDir = $this->testUploadPath . '/' . $datePath;
    
    expect(is_dir($expectedDir))->toBeTrue();
    
    unlink($sourceFile);
});

// StoredFile tests

it('creates StoredFile with correct properties', function () {
    $file = new StoredFile(
        filename: 'test-abc123.jpg',
        path: '2026/02/test-abc123.jpg',
        fullPath: '/var/www/uploads/2026/02/test-abc123.jpg',
        mimeType: 'image/jpeg',
        size: 1024,
        url: '/uploads/2026/02/test-abc123.jpg'
    );
    
    expect($file->filename)->toBe('test-abc123.jpg')
        ->and($file->path)->toBe('2026/02/test-abc123.jpg')
        ->and($file->mimeType)->toBe('image/jpeg')
        ->and($file->size)->toBe(1024)
        ->and($file->url)->toBe('/uploads/2026/02/test-abc123.jpg');
});

it('detects if file is an image', function () {
    $imageFile = new StoredFile(
        filename: 'test.jpg',
        path: '2026/02/test.jpg',
        fullPath: '/var/www/uploads/2026/02/test.jpg',
        mimeType: 'image/jpeg',
        size: 1024,
        url: '/uploads/2026/02/test.jpg'
    );
    
    $textFile = new StoredFile(
        filename: 'test.txt',
        path: '2026/02/test.txt',
        fullPath: '/var/www/uploads/2026/02/test.txt',
        mimeType: 'text/plain',
        size: 100,
        url: '/uploads/2026/02/test.txt'
    );
    
    expect($imageFile->isImage())->toBeTrue()
        ->and($textFile->isImage())->toBeFalse();
});

it('gets file extension', function () {
    $file = new StoredFile(
        filename: 'test-abc123.jpg',
        path: '2026/02/test-abc123.jpg',
        fullPath: '/var/www/uploads/2026/02/test-abc123.jpg',
        mimeType: 'image/jpeg',
        size: 1024,
        url: '/uploads/2026/02/test-abc123.jpg'
    );
    
    expect($file->getExtension())->toBe('jpg');
});

it('converts StoredFile to array', function () {
    $file = new StoredFile(
        filename: 'test.jpg',
        path: '2026/02/test.jpg',
        fullPath: '/var/www/uploads/2026/02/test.jpg',
        mimeType: 'image/jpeg',
        size: 1024,
        url: '/uploads/2026/02/test.jpg'
    );
    
    $array = $file->toArray();
    
    expect($array)->toHaveKey('filename')
        ->and($array)->toHaveKey('path')
        ->and($array)->toHaveKey('mime_type')
        ->and($array)->toHaveKey('size')
        ->and($array)->toHaveKey('url')
        ->and($array)->toHaveKey('is_image')
        ->and($array['is_image'])->toBeTrue();
});
