<?php

use Framework\Core\Exceptions\ValidationException;
use Framework\Core\Exceptions\AuthenticationException;
use Framework\Core\Exceptions\AuthorizationException;
use Framework\Core\Exceptions\NotFoundException;
use Framework\Core\Exceptions\RateLimitException;
use Framework\Core\Exceptions\ServerException;

it('creates validation exception with errors', function () {
    $errors = ['email' => 'Invalid email format'];
    $exception = new ValidationException('Validation failed', $errors);
    
    expect($exception->getStatusCode())->toBe(400)
        ->and($exception->getErrorCode())->toBe('VALIDATION_ERROR')
        ->and($exception->getErrors())->toBe($errors);
});

it('converts validation exception to response', function () {
    $errors = ['email' => 'Invalid email'];
    $exception = new ValidationException('Validation failed', $errors);
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(400)
        ->and($content['error']['code'])->toBe('VALIDATION_ERROR')
        ->and($content['error']['message'])->toBe('Validation failed')
        ->and($content['error']['details']['validation_errors'])->toBe($errors);
});

it('creates authentication exception', function () {
    $exception = new AuthenticationException('Invalid credentials');
    
    expect($exception->getStatusCode())->toBe(401)
        ->and($exception->getErrorCode())->toBe('AUTHENTICATION_REQUIRED')
        ->and($exception->getMessage())->toBe('Invalid credentials');
});

it('converts authentication exception to response', function () {
    $exception = new AuthenticationException();
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(401)
        ->and($content['error']['code'])->toBe('AUTHENTICATION_REQUIRED')
        ->and($content['error']['message'])->toBe('Valid authentication credentials are required');
});

it('creates authorization exception', function () {
    $exception = new AuthorizationException('Access denied');
    
    expect($exception->getStatusCode())->toBe(403)
        ->and($exception->getErrorCode())->toBe('INSUFFICIENT_PERMISSIONS');
});

it('converts authorization exception to response', function () {
    $exception = new AuthorizationException();
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(403)
        ->and($content['error']['code'])->toBe('INSUFFICIENT_PERMISSIONS');
});

it('creates not found exception', function () {
    $exception = new NotFoundException('Resource not found');
    
    expect($exception->getStatusCode())->toBe(404)
        ->and($exception->getErrorCode())->toBe('RESOURCE_NOT_FOUND');
});

it('converts not found exception to response', function () {
    $exception = new NotFoundException('User not found');
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(404)
        ->and($content['error']['message'])->toBe('User not found');
});

it('creates rate limit exception with retry after', function () {
    $exception = new RateLimitException(120);
    
    expect($exception->getStatusCode())->toBe(429)
        ->and($exception->getErrorCode())->toBe('RATE_LIMIT_EXCEEDED')
        ->and($exception->getRetryAfter())->toBe(120);
});

it('converts rate limit exception to response with header', function () {
    $exception = new RateLimitException(60);
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(429)
        ->and($response->getHeader('Retry-After'))->toBe('60')
        ->and($content['error']['details']['retry_after'])->toBe(60);
});

it('creates server exception', function () {
    $exception = new ServerException('Database connection failed');
    
    expect($exception->getStatusCode())->toBe(500)
        ->and($exception->getErrorCode())->toBe('INTERNAL_SERVER_ERROR');
});

it('converts server exception to response', function () {
    $exception = new ServerException();
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(500)
        ->and($content['error']['code'])->toBe('INTERNAL_SERVER_ERROR')
        ->and($content['error']['message'])->toBe('An unexpected error occurred');
});

it('includes timestamp in error response', function () {
    $exception = new NotFoundException();
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($content['error']['timestamp'])->toBeString()
        ->and($content['error']['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

it('omits details when empty', function () {
    $exception = new AuthenticationException();
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($content['error'])->not->toHaveKey('details');
});

it('includes details when provided', function () {
    $exception = new ValidationException('Error', ['field' => 'error']);
    
    $response = $exception->toResponse();
    $content = json_decode($response->getContent(), true);
    
    expect($content['error'])->toHaveKey('details');
});
