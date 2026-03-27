<?php

use ChimeraNoWP\Core\RateLimiter;
use ChimeraNoWP\Cache\CacheManager;
use ChimeraNoWP\Cache\FileAdapter;

beforeEach(function () {
    $adapter = new FileAdapter(__DIR__ . '/../../fixtures/cache');
    $this->cache = new CacheManager($adapter);
    $this->limiter = new RateLimiter($this->cache);
});

afterEach(function () {
    // Clean up cache files
    $cacheDir = __DIR__ . '/../../fixtures/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});

it('tracks attempts', function () {
    $key = 'test-key';
    
    expect($this->limiter->attempts($key))->toBe(0);
    
    $this->limiter->hit($key);
    expect($this->limiter->attempts($key))->toBe(1);
    
    $this->limiter->hit($key);
    expect($this->limiter->attempts($key))->toBe(2);
});

it('detects too many attempts', function () {
    $key = 'test-key';
    $maxAttempts = 3;
    $decaySeconds = 60;
    
    expect($this->limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds))->toBeFalse();
    
    $this->limiter->hit($key, $decaySeconds);
    $this->limiter->hit($key, $decaySeconds);
    expect($this->limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds))->toBeFalse();
    
    $this->limiter->hit($key, $decaySeconds);
    expect($this->limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds))->toBeTrue();
});

it('resets attempts', function () {
    $key = 'test-key';
    
    $this->limiter->hit($key);
    $this->limiter->hit($key);
    expect($this->limiter->attempts($key))->toBe(2);
    
    $this->limiter->resetAttempts($key);
    expect($this->limiter->attempts($key))->toBe(0);
});

it('returns availability time', function () {
    $key = 'test-key';
    $decaySeconds = 60;
    
    expect($this->limiter->availableIn($key, $decaySeconds))->toBe(0);
    
    $this->limiter->hit($key, $decaySeconds);
    expect($this->limiter->availableIn($key, $decaySeconds))->toBe($decaySeconds);
});

it('handles multiple keys independently', function () {
    $key1 = 'user-1';
    $key2 = 'user-2';
    
    $this->limiter->hit($key1);
    $this->limiter->hit($key1);
    $this->limiter->hit($key2);
    
    expect($this->limiter->attempts($key1))->toBe(2);
    expect($this->limiter->attempts($key2))->toBe(1);
});

it('increments and returns current attempts', function () {
    $key = 'test-key';
    
    $attempts = $this->limiter->hit($key);
    expect($attempts)->toBe(1);
    
    $attempts = $this->limiter->hit($key);
    expect($attempts)->toBe(2);
});
