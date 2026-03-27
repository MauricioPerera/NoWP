<?php

use ChimeraNoWP\Storage\FileManager;

beforeEach(function () {
    $this->uploadPath = __DIR__ . '/../../fixtures/uploads';
    $this->fileManager = new FileManager($this->uploadPath, '/uploads');
    
    // Create upload directory
    if (!is_dir($this->uploadPath)) {
        mkdir($this->uploadPath, 0755, true);
    }
});

afterEach(function () {
    // Clean up test files
    if (is_dir($this->uploadPath)) {
        removeDirectory($this->uploadPath);
    }
});

test('finds orphaned files', function () {
    // Create some test files
    $referencedFile = $this->uploadPath . '/2026/01/referenced-file.jpg';
    $orphanFile = $this->uploadPath . '/2026/01/orphan-file.jpg';
    
    mkdir(dirname($referencedFile), 0755, true);
    file_put_contents($referencedFile, 'test content');
    file_put_contents($orphanFile, 'orphan content');
    
    // Callback that only references the first file
    $isReferenced = function ($path) {
        return str_contains($path, 'referenced-file');
    };
    
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: true);
    
    expect($orphans)->toHaveCount(1);
    expect($orphans[0])->toContain('orphan-file.jpg');
});

test('deletes orphaned files when not in dry run mode', function () {
    // Create orphan file
    $orphanFile = $this->uploadPath . '/2026/01/orphan.jpg';
    mkdir(dirname($orphanFile), 0755, true);
    file_put_contents($orphanFile, 'orphan content');
    
    expect(file_exists($orphanFile))->toBeTrue();
    
    // Clean orphans (no files are referenced)
    $isReferenced = fn($path) => false;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: false);
    
    expect($orphans)->toHaveCount(1);
    expect(file_exists($orphanFile))->toBeFalse();
});

test('does not delete files in dry run mode', function () {
    // Create orphan file
    $orphanFile = $this->uploadPath . '/2026/01/orphan.jpg';
    mkdir(dirname($orphanFile), 0755, true);
    file_put_contents($orphanFile, 'orphan content');
    
    // Clean orphans in dry run mode
    $isReferenced = fn($path) => false;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: true);
    
    expect($orphans)->toHaveCount(1);
    expect(file_exists($orphanFile))->toBeTrue(); // File still exists
});

test('keeps referenced files', function () {
    // Create referenced file
    $referencedFile = $this->uploadPath . '/2026/01/referenced.jpg';
    mkdir(dirname($referencedFile), 0755, true);
    file_put_contents($referencedFile, 'referenced content');
    
    // All files are referenced
    $isReferenced = fn($path) => true;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: false);
    
    expect($orphans)->toBeEmpty();
    expect(file_exists($referencedFile))->toBeTrue();
});

test('scans directories recursively', function () {
    // Create files in multiple directories
    $files = [
        $this->uploadPath . '/2026/01/file1.jpg',
        $this->uploadPath . '/2026/02/file2.jpg',
        $this->uploadPath . '/2025/12/file3.jpg',
    ];
    
    foreach ($files as $file) {
        mkdir(dirname($file), 0755, true);
        file_put_contents($file, 'content');
    }
    
    // No files are referenced
    $isReferenced = fn($path) => false;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: true);
    
    expect($orphans)->toHaveCount(3);
});

test('ignores .gitkeep files', function () {
    // Create .gitkeep file
    $gitkeep = $this->uploadPath . '/.gitkeep';
    file_put_contents($gitkeep, '');
    
    // Create regular file
    $regularFile = $this->uploadPath . '/file.jpg';
    file_put_contents($regularFile, 'content');
    
    // No files are referenced
    $isReferenced = fn($path) => false;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: true);
    
    // Should only find the regular file, not .gitkeep
    expect($orphans)->toHaveCount(1);
    expect($orphans[0])->toBe('file.jpg');
});

test('handles empty upload directory', function () {
    // Empty directory
    $isReferenced = fn($path) => false;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: true);
    
    expect($orphans)->toBeEmpty();
});

test('returns relative paths', function () {
    // Create file
    $file = $this->uploadPath . '/2026/01/test.jpg';
    mkdir(dirname($file), 0755, true);
    file_put_contents($file, 'content');
    
    $isReferenced = fn($path) => false;
    $orphans = $this->fileManager->cleanOrphans($isReferenced, dryRun: true);
    
    expect($orphans)->toHaveCount(1);
    expect($orphans[0])->toBe('2026/01/test.jpg');
    expect($orphans[0])->not->toContain($this->uploadPath);
});
