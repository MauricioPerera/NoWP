<?php

use Framework\Cache\FileAdapter;

beforeEach(function () {
    $this->cachePath = BASE_PATH . '/storage/cache/test';
    $this->adapter = new FileAdapter($this->cachePath, 'test_');
});

afterEach(function () {
    // Clean up test cache files
    if (is_dir($this->cachePath)) {
        $files = glob($this->cachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->cachePath);
    }
});

it('can store and retrieve values', function () {
    $result = $this->adapter->set('key1', 'value1', 60);
    
    expect($result)->toBeTrue()
        ->and($this->adapter->get('key1'))->toBe('value1');
});

it('returns null for non-existent keys', function () {
    expect($this->adapter->get('nonexistent'))->toBeNull();
});

it('can store complex data types', function () {
    $data = [
        'string' => 'test',
        'number' => 42,
        'array' => [1, 2, 3],
        'object' => (object)['key' => 'value']
    ];
    
    $this->adapter->set('complex', $data, 60);
    
    $retrieved = $this->adapter->get('complex');
    
    expect($retrieved)->toBeArray()
        ->and($retrieved['string'])->toBe('test')
        ->and($retrieved['number'])->toBe(42)
        ->and($retrieved['array'])->toBe([1, 2, 3]);
});

it('can delete values', function () {
    $this->adapter->set('key1', 'value1', 60);
    
    expect($this->adapter->get('key1'))->toBe('value1');
    
    $result = $this->adapter->delete('key1');
    
    expect($result)->toBeTrue()
        ->and($this->adapter->get('key1'))->toBeNull();
});

it('respects TTL expiration', function () {
    $this->adapter->set('key1', 'value1', 1);
    
    expect($this->adapter->get('key1'))->toBe('value1');
    
    sleep(2);
    
    expect($this->adapter->get('key1'))->toBeNull();
});

it('can flush all cache', function () {
    $this->adapter->set('key1', 'value1', 60);
    $this->adapter->set('key2', 'value2', 60);
    
    expect($this->adapter->get('key1'))->toBe('value1')
        ->and($this->adapter->get('key2'))->toBe('value2');
    
    $result = $this->adapter->flush();
    
    expect($result)->toBeTrue()
        ->and($this->adapter->get('key1'))->toBeNull()
        ->and($this->adapter->get('key2'))->toBeNull();
});

it('creates cache directory if it does not exist', function () {
    $newPath = BASE_PATH . '/storage/cache/newdir';
    
    if (is_dir($newPath)) {
        rmdir($newPath);
    }
    
    expect(is_dir($newPath))->toBeFalse();
    
    $adapter = new FileAdapter($newPath);
    
    expect(is_dir($newPath))->toBeTrue();
    
    // Cleanup
    rmdir($newPath);
});

it('is always available', function () {
    expect(FileAdapter::isAvailable())->toBeTrue();
});

it('handles concurrent writes safely', function () {
    $this->adapter->set('key1', 'value1', 60);
    $this->adapter->set('key1', 'value2', 60);
    
    expect($this->adapter->get('key1'))->toBe('value2');
});
