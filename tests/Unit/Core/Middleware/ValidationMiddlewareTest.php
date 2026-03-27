<?php

use ChimeraNoWP\Core\Middleware\ValidationMiddleware;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Exceptions\ValidationException;

it('validates and sanitizes data', function () {
    $middleware = new ValidationMiddleware([
        'name' => 'required'
    ]);
    
    $request = new Request('POST', '/test', [], [
        'name' => '  John Doe  '
    ]);
    
    // Validation should pass (sanitization happens internally)
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('validates required fields', function () {
    $middleware = new ValidationMiddleware([
        'name' => 'required',
        'email' => 'required'
    ]);
    
    $request = new Request('POST', '/test', [], ['name' => 'John']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('email')
            ->and($errors['email'])->toContain('required');
    }
});

it('validates email format', function () {
    $middleware = new ValidationMiddleware([
        'email' => 'email'
    ]);
    
    $request = new Request('POST', '/test', [], ['email' => 'invalid-email']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('email')
            ->and($errors['email'])->toContain('valid email');
    }
});

it('validates minimum length', function () {
    $middleware = new ValidationMiddleware([
        'password' => 'min:8'
    ]);
    
    $request = new Request('POST', '/test', [], ['password' => 'short']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('password')
            ->and($errors['password'])->toContain('at least 8 characters');
    }
});

it('validates maximum length', function () {
    $middleware = new ValidationMiddleware([
        'username' => 'max:20'
    ]);
    
    $request = new Request('POST', '/test', [], ['username' => str_repeat('a', 25)]);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('username')
            ->and($errors['username'])->toContain('not exceed 20 characters');
    }
});

it('validates numeric values', function () {
    $middleware = new ValidationMiddleware([
        'age' => 'numeric'
    ]);
    
    $request = new Request('POST', '/test', [], ['age' => 'not-a-number']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('age')
            ->and($errors['age'])->toContain('must be a number');
    }
});

it('validates integer values', function () {
    $middleware = new ValidationMiddleware([
        'count' => 'integer'
    ]);
    
    $request = new Request('POST', '/test', [], ['count' => '3.14']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('count')
            ->and($errors['count'])->toContain('must be an integer');
    }
});

it('validates URL format', function () {
    $middleware = new ValidationMiddleware([
        'website' => 'url'
    ]);
    
    $request = new Request('POST', '/test', [], ['website' => 'not-a-url']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('website')
            ->and($errors['website'])->toContain('valid URL');
    }
});

it('validates value is in allowed list', function () {
    $middleware = new ValidationMiddleware([
        'status' => 'in:active,inactive,pending'
    ]);
    
    $request = new Request('POST', '/test', [], ['status' => 'invalid']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('status')
            ->and($errors['status'])->toContain('must be one of');
    }
});

it('validates regex pattern', function () {
    $middleware = new ValidationMiddleware([
        'code' => 'regex:/^[A-Z]{3}$/'
    ]);
    
    $request = new Request('POST', '/test', [], ['code' => 'abc']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('code')
            ->and($errors['code'])->toContain('format is invalid');
    }
});

it('passes validation with valid data', function () {
    $middleware = new ValidationMiddleware([
        'name' => 'required|string|min:3|max:50',
        'email' => 'required|email',
        'age' => 'numeric|min:18|max:120'
    ]);
    
    $request = new Request('POST', '/test', [], [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 25
    ]);
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('skips validation for optional fields', function () {
    $middleware = new ValidationMiddleware([
        'email' => 'email',
        'age' => 'numeric'
    ]);
    
    $request = new Request('POST', '/test', [], ['name' => 'John']);
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('handles nested data structures', function () {
    $middleware = new ValidationMiddleware([
        'name' => 'required'
    ]);
    
    $request = new Request('POST', '/test', [], [
        'name' => 'John',
        'users' => [
            ['name' => '  Alice  '],
            ['name' => '  Bob  ']
        ]
    ]);
    
    $response = $middleware->handle($request, fn($req) => new Response('OK', 200));
    
    expect($response->getStatusCode())->toBe(200);
});

it('validates multiple rules for a field', function () {
    $middleware = new ValidationMiddleware([
        'username' => 'required|string|min:3|max:20'
    ]);
    
    $request = new Request('POST', '/test', [], ['username' => 'ab']);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('username')
            ->and($errors['username'])->toContain('at least 3 characters');
    }
});

it('stops at first validation error per field', function () {
    $middleware = new ValidationMiddleware([
        'email' => 'required|email'
    ]);
    
    $request = new Request('POST', '/test', [], []);
    
    try {
        $middleware->handle($request, fn($req) => new Response('OK', 200));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        $errors = $e->getErrors();
        expect($errors)->toHaveKey('email')
            ->and($errors['email'])->toContain('required');
    }
});
