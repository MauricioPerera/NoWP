<?php

use ChimeraNoWP\Cache\CacheManager;
use ChimeraNoWP\Cache\NullCacheAdapter;
use ChimeraNoWP\Cache\FileAdapter;
use ChimeraNoWP\Cache\APCuAdapter;

beforeEach(function () {
    $this->cachePath = BASE_PATH . '/storage/cache/test';
    $this->adapter = new FileAdapter($this->cachePath, 'test_');
    $this->manager = new CacheManager($this->adapter);
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

it('can remember values with callback', function () {
    $callCount = 0;
    
    $value = $this->manager->remember('key1', 60, function () use (&$callCount) {
        $callCount++;
        return 'computed value';
    });
    
    expect($value)->toBe('computed value')
        ->and($callCount)->toBe(1);
    
    // Second call should use cached value
    $value2 = $this->manager->remember('key1', 60, function () use (&$callCount) {
        $callCount++;
        return 'computed value';
    });
    
    expect($value2)->toBe('computed value')
        ->and($callCount)->toBe(1); // Callback not called again
});

it('can get and set values directly', function () {
    $result = $this->manager->set('key1', 'value1', 60);
    
    expect($result)->toBeTrue()
        ->and($this->manager->get('key1'))->toBe('value1');
});

it('can delete values', function () {
    $this->manager->set('key1', 'value1', 60);
    
    expect($this->manager->get('key1'))->toBe('value1');
    
    $result = $this->manager->delete('key1');
    
    expect($result)->toBeTrue()
        ->and($this->manager->get('key1'))->toBeNull();
});

it('can invalidate cache by key', function () {
    $this->manager->set('key1', 'value1', 60);
    $this->manager->set('key2', 'value2', 60);
    
    $this->manager->invalidate('key1');
    
    expect($this->manager->get('key1'))->toBeNull()
        ->and($this->manager->get('key2'))->toBe('value2');
});

it('can invalidate cache by tags', function () {
    $this->manager->set('tag1', 'value1', 60);
    $this->manager->set('tag2', 'value2', 60);
    
    $this->manager->invalidate(['tag1', 'tag2']);
    
    expect($this->manager->get('tag1'))->toBeNull()
        ->and($this->manager->get('tag2'))->toBeNull();
});

it('can set tags for operations', function () {
    $manager = $this->manager->tags(['content', 'post']);
    
    expect($manager)->toBeInstanceOf(CacheManager::class);
});

it('can flush all cache', function () {
    $this->manager->set('key1', 'value1', 60);
    $this->manager->set('key2', 'value2', 60);
    
    $result = $this->manager->flush();
    
    expect($result)->toBeTrue()
        ->and($this->manager->get('key1'))->toBeNull()
        ->and($this->manager->get('key2'))->toBeNull();
});

it('auto-detects file adapter when no cache system available', function () {
    $manager = new CacheManager();
    $adapter = $manager->getAdapter();
    
    // Should detect and use an available adapter
    expect($adapter)->toBeInstanceOf(\ChimeraNoWP\Cache\CacheAdapterInterface::class);
});

it('uses provided adapter instead of auto-detection', function () {
    $customAdapter = new NullCacheAdapter();
    $manager = new CacheManager($customAdapter);
    
    expect($manager->getAdapter())->toBe($customAdapter);
});

it('auto-detects APCu when available', function () {
    if (!APCuAdapter::isAvailable()) {
        $this->markTestSkipped('APCu not available');
    }
    
    $manager = new CacheManager();
    $adapter = $manager->getAdapter();
    
    // Should prefer APCu over file cache
    expect($adapter)->toBeInstanceOf(APCuAdapter::class);
});

it('falls back to file adapter when no other cache available', function () {
    // Create manager without providing adapter
    $manager = new CacheManager();
    
    // Should have some adapter (not null)
    expect($manager->getAdapter())->not->toBeNull();
});

it('handles remember with complex data', function () {
    $data = [
        'users' => [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ],
        'meta' => (object)['total' => 2]
    ];
    
    $result = $this->manager->remember('users', 60, fn() => $data);
    
    expect($result)->toBeArray()
        ->and($result['users'])->toHaveCount(2)
        ->and($result['meta']->total)->toBe(2);
});
