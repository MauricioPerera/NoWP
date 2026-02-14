<?php

use Framework\Core\ExceptionHandler;
use Framework\Core\Exceptions\ValidationException;
use Framework\Core\Exceptions\NotFoundException;
use Framework\Core\Exceptions\ServerException;

beforeEach(function () {
    $this->logPath = BASE_PATH . '/storage/logs/test-errors.log';
    
    // Clean up log file
    if (file_exists($this->logPath)) {
        unlink($this->logPath);
    }
    
    $this->handler = new ExceptionHandler(false, $this->logPath);
});

afterEach(function () {
    // Clean up log file
    if (file_exists($this->logPath)) {
        unlink($this->logPath);
    }
});

it('handles HTTP exceptions', function () {
    $exception = new NotFoundException('Resource not found');
    
    $response = $this->handler->handle($exception);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(404)
        ->and($content['error']['code'])->toBe('RESOURCE_NOT_FOUND');
});

it('handles validation exceptions with errors', function () {
    $exception = new ValidationException('Invalid data', ['email' => 'Required']);
    
    $response = $this->handler->handle($exception);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(400)
        ->and($content['error']['details']['validation_errors'])->toBe(['email' => 'Required']);
});

it('converts generic exceptions to server error in production', function () {
    $handler = new ExceptionHandler(false);
    $exception = new \RuntimeException('Something went wrong');
    
    $response = $handler->handle($exception);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(500)
        ->and($content['error']['code'])->toBe('INTERNAL_SERVER_ERROR')
        ->and($content['error']['message'])->toBe('An unexpected error occurred');
});

it('shows detailed error in debug mode', function () {
    $handler = new ExceptionHandler(true);
    $exception = new \RuntimeException('Debug error');
    
    $response = $handler->handle($exception);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(500)
        ->and($content['error']['message'])->toBe('Debug error')
        ->and($content['error']['exception'])->toBe('RuntimeException')
        ->and($content['error'])->toHaveKey('file')
        ->and($content['error'])->toHaveKey('line')
        ->and($content['error'])->toHaveKey('trace');
});

it('logs exceptions to file', function () {
    $exception = new NotFoundException('Test not found');
    
    $this->handler->handle($exception);
    
    expect(file_exists($this->logPath))->toBeTrue();
    
    $logContent = file_get_contents($this->logPath);
    
    expect($logContent)->toContain('NotFoundException')
        ->and($logContent)->toContain('Test not found')
        ->and($logContent)->toContain('RESOURCE_NOT_FOUND');
});

it('logs with appropriate log level', function () {
    // 4xx errors should be WARNING
    $exception = new NotFoundException();
    $this->handler->handle($exception);
    
    $logContent = file_get_contents($this->logPath);
    expect($logContent)->toContain('[WARNING]');
    
    // Clean log
    unlink($this->logPath);
    
    // 5xx errors should be ERROR
    $exception = new ServerException();
    $this->handler->handle($exception);
    
    $logContent = file_get_contents($this->logPath);
    expect($logContent)->toContain('[ERROR]');
});

it('creates log directory if not exists', function () {
    $logPath = BASE_PATH . '/storage/logs/nested/test.log';
    $handler = new ExceptionHandler(false, $logPath);
    
    $exception = new NotFoundException();
    $handler->handle($exception);
    
    expect(file_exists($logPath))->toBeTrue();
    
    // Cleanup
    unlink($logPath);
    rmdir(dirname($logPath));
});

it('handles exceptions without logging when no log path', function () {
    $handler = new ExceptionHandler(false, null);
    $exception = new NotFoundException();
    
    $response = $handler->handle($exception);
    
    expect($response->getStatusCode())->toBe(404);
    // Should not throw error even without log path
});

it('logs previous exceptions', function () {
    $previous = new \RuntimeException('Previous error');
    $exception = new ServerException('Main error', $previous);
    
    $this->handler->handle($exception);
    
    $logContent = file_get_contents($this->logPath);
    
    expect($logContent)->toContain('Main error')
        ->and($logContent)->toContain('Previous exception')
        ->and($logContent)->toContain('Previous error');
});

it('limits stack trace in debug mode', function () {
    $handler = new ExceptionHandler(true);
    $exception = new \RuntimeException('Test');
    
    $response = $handler->handle($exception);
    $content = json_decode($response->getContent(), true);
    
    expect($content['error']['trace'])->toBeArray()
        ->and(count($content['error']['trace']))->toBeLessThanOrEqual(10);
});

it('formats trace with file, line, function, and class', function () {
    $handler = new ExceptionHandler(true);
    $exception = new \RuntimeException('Test');
    
    $response = $handler->handle($exception);
    $content = json_decode($response->getContent(), true);
    
    $firstFrame = $content['error']['trace'][0];
    
    expect($firstFrame)->toHaveKey('file')
        ->and($firstFrame)->toHaveKey('line')
        ->and($firstFrame)->toHaveKey('function')
        ->and($firstFrame)->toHaveKey('class');
});
