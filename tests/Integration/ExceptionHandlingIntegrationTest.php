<?php

use Framework\Core\Application;
use Framework\Core\Request;
use Framework\Core\Router;
use Framework\Core\Exceptions\NotFoundException;
use Framework\Core\Exceptions\ValidationException;

beforeEach(function () {
    $this->app = new Application([
        'app' => [
            'debug' => false,
            'log' => [
                'path' => BASE_PATH . '/storage/logs/test'
            ]
        ]
    ]);
    
    $this->app->boot();
});

afterEach(function () {
    // Clean up test logs
    $logFile = BASE_PATH . '/storage/logs/test/error.log';
    if (file_exists($logFile)) {
        unlink($logFile);
    }
});

it('handles route not found', function () {
    $request = new Request('GET', '/nonexistent');
    
    $response = $this->app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(404)
        ->and($content['error']['code'])->toBe('ROUTE_NOT_FOUND');
});

it('handles exceptions thrown in route handlers', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->get('/error', function () {
        throw new NotFoundException('Resource not found');
    });
    
    $request = new Request('GET', '/error');
    $response = $this->app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(404)
        ->and($content['error']['code'])->toBe('RESOURCE_NOT_FOUND')
        ->and($content['error']['message'])->toBe('Resource not found');
});

it('handles validation exceptions', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->post('/validate', function () {
        throw new ValidationException('Invalid data', ['email' => 'Required field']);
    });
    
    $request = new Request('POST', '/validate');
    $response = $this->app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(400)
        ->and($content['error']['code'])->toBe('VALIDATION_ERROR')
        ->and($content['error']['details']['validation_errors'])->toBe(['email' => 'Required field']);
});

it('converts generic exceptions to server errors in production', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->get('/crash', function () {
        throw new \RuntimeException('Something went wrong');
    });
    
    $request = new Request('GET', '/crash');
    $response = $this->app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(500)
        ->and($content['error']['code'])->toBe('INTERNAL_SERVER_ERROR')
        ->and($content['error']['message'])->toBe('An unexpected error occurred');
});

it('shows detailed errors in debug mode', function () {
    $app = new Application([
        'app' => [
            'debug' => true,
            'log' => [
                'path' => BASE_PATH . '/storage/logs/test'
            ]
        ]
    ]);
    
    $app->boot();
    
    $router = $app->getContainer()->resolve(Router::class);
    
    $router->get('/debug-error', function () {
        throw new \RuntimeException('Debug error message');
    });
    
    $request = new Request('GET', '/debug-error');
    $response = $app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(500)
        ->and($content['error']['message'])->toBe('Debug error message')
        ->and($content['error']['exception'])->toBe('RuntimeException')
        ->and($content['error'])->toHaveKey('file')
        ->and($content['error'])->toHaveKey('line')
        ->and($content['error'])->toHaveKey('trace');
});

it('logs exceptions to file', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->get('/log-test', function () {
        throw new NotFoundException('Test logging');
    });
    
    $request = new Request('GET', '/log-test');
    $this->app->handle($request);
    
    $logFile = BASE_PATH . '/storage/logs/test/error.log';
    
    expect(file_exists($logFile))->toBeTrue();
    
    $logContent = file_get_contents($logFile);
    
    expect($logContent)->toContain('NotFoundException')
        ->and($logContent)->toContain('Test logging');
});

it('handles exceptions in middleware', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->get('/middleware-error', function () {
        return new \Framework\Core\Response('Success', 200);
    })->middleware(function ($request, $next) {
        throw new \Framework\Core\Exceptions\AuthenticationException('Token required');
    });
    
    $request = new Request('GET', '/middleware-error');
    $response = $this->app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($response->getStatusCode())->toBe(401)
        ->and($content['error']['code'])->toBe('AUTHENTICATION_REQUIRED');
});

it('includes timestamp in error responses', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->get('/timestamp', function () {
        throw new NotFoundException();
    });
    
    $request = new Request('GET', '/timestamp');
    $response = $this->app->handle($request);
    $content = json_decode($response->getContent(), true);
    
    expect($content['error']['timestamp'])->toBeString()
        ->and($content['error']['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

it('handles multiple exceptions in sequence', function () {
    $router = $this->app->getContainer()->resolve(Router::class);
    
    $router->get('/error1', function () {
        throw new NotFoundException('Error 1');
    });
    
    $router->get('/error2', function () {
        throw new ValidationException('Error 2', ['field' => 'error']);
    });
    
    $request1 = new Request('GET', '/error1');
    $response1 = $this->app->handle($request1);
    
    $request2 = new Request('GET', '/error2');
    $response2 = $this->app->handle($request2);
    
    expect($response1->getStatusCode())->toBe(404)
        ->and($response2->getStatusCode())->toBe(400);
});
