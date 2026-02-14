<?php

use Framework\Core\Middleware\CSRFMiddleware;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Core\Exceptions\AuthorizationException;

it('allows GET requests without CSRF token', function () {
    $middleware = new CSRFMiddleware();
    $request = new Request('GET', '/api/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('generates CSRF token', function () {
    $middleware = new CSRFMiddleware();
    
    $token = $middleware->generateToken();
    
    expect($token)->toBeString();
    expect(strlen($token))->toBe(64); // 32 bytes = 64 hex chars
});

it('returns same token on subsequent calls', function () {
    $middleware = new CSRFMiddleware();
    
    $token1 = $middleware->getToken();
    $token2 = $middleware->getToken();
    
    expect($token1)->toBe($token2);
});

it('validates CSRF token from header', function () {
    $middleware = new CSRFMiddleware();
    $token = $middleware->generateToken();
    
    $request = new Request('POST', '/api/test', ['X-CSRF-Token' => $token]);
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('validates CSRF token from request body', function () {
    $middleware = new CSRFMiddleware();
    $token = $middleware->generateToken();
    
    $request = new Request('POST', '/api/test', [], [], ['csrf_token' => $token]);
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('rejects POST request without CSRF token', function () {
    $middleware = new CSRFMiddleware();
    $middleware->generateToken(); // Generate token but don't send it
    
    $request = new Request('POST', '/api/test');
    
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(AuthorizationException::class, 'CSRF token missing');

it('rejects request with invalid CSRF token', function () {
    $middleware = new CSRFMiddleware();
    $middleware->generateToken();
    
    $request = new Request('POST', '/api/test', ['X-CSRF-Token' => 'invalid-token']);
    
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(AuthorizationException::class, 'CSRF token mismatch');

it('checks CSRF for PUT requests', function () {
    $middleware = new CSRFMiddleware();
    $middleware->generateToken();
    
    $request = new Request('PUT', '/api/test');
    
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(AuthorizationException::class);

it('checks CSRF for DELETE requests', function () {
    $middleware = new CSRFMiddleware();
    $middleware->generateToken();
    
    $request = new Request('DELETE', '/api/test');
    
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(AuthorizationException::class);

it('checks CSRF for PATCH requests', function () {
    $middleware = new CSRFMiddleware();
    $middleware->generateToken();
    
    $request = new Request('PATCH', '/api/test');
    
    $middleware->handle($request, fn($req) => new Response('OK', 200));
})->throws(AuthorizationException::class);

it('allows HEAD requests without CSRF token', function () {
    $middleware = new CSRFMiddleware();
    $request = new Request('HEAD', '/api/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('allows OPTIONS requests without CSRF token', function () {
    $middleware = new CSRFMiddleware();
    $request = new Request('OPTIONS', '/api/test');
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});
