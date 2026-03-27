<?php

use ChimeraNoWP\Core\Middleware\SecurityHeadersMiddleware;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;

it('adds X-Frame-Options header', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN');
});

it('adds X-Content-Type-Options header', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-Content-Type-Options'))->toBe('nosniff');
});

it('adds X-XSS-Protection header', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-XSS-Protection'))->toBe('1; mode=block');
});

it('adds Referrer-Policy header', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});

it('adds Content-Security-Policy header', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    $csp = $response->getHeader('Content-Security-Policy');
    
    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("script-src 'self'");
});

it('adds Permissions-Policy header', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('Permissions-Policy'))->toContain('geolocation=()');
});

it('allows custom headers', function () {
    $middleware = new SecurityHeadersMiddleware([
        'X-Custom-Header' => 'custom-value'
    ]);
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-Custom-Header'))->toBe('custom-value');
});

it('custom headers override defaults', function () {
    $middleware = new SecurityHeadersMiddleware([
        'X-Frame-Options' => 'DENY'
    ]);
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-Frame-Options'))->toBe('DENY');
});

it('adds all security headers to response', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getHeader('X-Frame-Options'))->not->toBeNull()
        ->and($response->getHeader('X-Content-Type-Options'))->not->toBeNull()
        ->and($response->getHeader('X-XSS-Protection'))->not->toBeNull()
        ->and($response->getHeader('Referrer-Policy'))->not->toBeNull()
        ->and($response->getHeader('Content-Security-Policy'))->not->toBeNull()
        ->and($response->getHeader('Permissions-Policy'))->not->toBeNull();
});

it('does not modify request object', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test', ['X-Test' => 'value']);
    
    $middleware->handle($request, function ($req) {
        // Request should remain unchanged
        expect($req->getMethod())->toBe('GET');
        return new Response('OK', 200);
    });
});

it('preserves existing response headers', function () {
    $middleware = new SecurityHeadersMiddleware();
    $request = new Request('GET', '/test');
    
    $response = $middleware->handle($request, function ($req) {
        return new Response('OK', 200, ['X-Custom' => 'preserved']);
    });
    
    expect($response->getHeader('X-Custom'))->toBe('preserved')
        ->and($response->getHeader('X-Frame-Options'))->toBe('SAMEORIGIN');
});
