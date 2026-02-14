<?php

use Framework\Core\Middleware\RateLimitMiddleware;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Core\RateLimiter;
use Framework\Core\Exceptions\RateLimitException;
use Framework\Cache\CacheManager;
use Framework\Cache\FileAdapter;

beforeEach(function () {
    $adapter = new FileAdapter(__DIR__ . '/../../../fixtures/cache');
    $cache = new CacheManager($adapter);
    $this->limiter = new RateLimiter($cache);
});

afterEach(function () {
    // Clean up cache files
    $cacheDir = __DIR__ . '/../../../fixtures/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});

it('allows requests within rate limit', function () {
    $middleware = new RateLimitMiddleware($this->limiter, 5, 60);
    $request = new Request('GET', '/api/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeader('X-RateLimit-Limit'))->toBe('5');
    expect($response->getHeader('X-RateLimit-Remaining'))->toBe('4');
});

it('blocks requests exceeding rate limit', function () {
    $middleware = new RateLimitMiddleware($this->limiter, 3, 60);
    $request = new Request('GET', '/api/test');
    
    // Make 3 requests (should succeed)
    $middleware->handle($request, fn($req) => new Response('OK', 200));
    $middleware->handle($request, fn($req) => new Response('OK', 200));
    $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    // 4th request should fail
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(RateLimitException::class);

it('adds rate limit headers to response', function () {
    $middleware = new RateLimitMiddleware($this->limiter, 10, 60);
    $request = new Request('GET', '/api/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-RateLimit-Limit'))->toBe('10');
    expect($response->getHeader('X-RateLimit-Remaining'))->toBe('9');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    expect($response->getHeader('X-RateLimit-Remaining'))->toBe('8');
});

it('tracks different paths separately', function () {
    $middleware1 = new RateLimitMiddleware($this->limiter, 2, 60);
    $middleware2 = new RateLimitMiddleware($this->limiter, 2, 60);
    
    $request1 = new Request('GET', '/api/endpoint1');
    $request2 = new Request('GET', '/api/endpoint2');
    
    // Each endpoint should have its own limit
    $middleware1->handle($request1, fn($req) => new Response('OK', 200));
    $middleware1->handle($request1, fn($req) => new Response('OK', 200));
    
    // Different endpoint should still work
    $response = $middleware2->handle($request2, fn($req) => new Response('OK', 200));
    expect($response->getStatusCode())->toBe(200);
});

it('uses configurable max attempts and decay', function () {
    $middleware = new RateLimitMiddleware($this->limiter, 2, 30);
    $request = new Request('GET', '/api/test');
    
    $middleware->handle($request, fn($req) => new Response('OK', 200));
    $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    // 3rd request should fail with custom limit
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(RateLimitException::class);
