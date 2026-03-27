<?php

use ChimeraNoWP\Cache\APCuAdapter;

beforeEach(function () {
    if (!APCuAdapter::isAvailable()) {
        $this->markTestSkipped('APCu extension not available');
    }
    
    // Clear cache before each test
    apcu_clear_cache();
    
    $this->adapter = new APCuAdapter('test_');
});

afterEach(function () {
    if (APCuAdapter::isAvailable()) {
        apcu_clear_cache();
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
})->skip(!APCuAdapter::isAvailable(), 'APCu not available');

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

it('uses prefix for keys', function () {
    $adapter1 = new APCuAdapter('prefix1_');
    $adapter2 = new APCuAdapter('prefix2_');
    
    $adapter1->set('key', 'value1', 60);
    $adapter2->set('key', 'value2', 60);
    
    expect($adapter1->get('key'))->toBe('value1')
        ->and($adapter2->get('key'))->toBe('value2');
});

it('detects APCu availability correctly', function () {
    $available = APCuAdapter::isAvailable();
    
    expect($available)->toBeBool();
    
    if (extension_loaded('apcu') && ini_get('apc.enabled')) {
        expect($available)->toBeTrue();
    }
});
