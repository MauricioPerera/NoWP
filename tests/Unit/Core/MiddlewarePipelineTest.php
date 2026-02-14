<?php

use Framework\Core\Container;
use Framework\Core\MiddlewareInterface;
use Framework\Core\MiddlewarePipeline;
use Framework\Core\Request;
use Framework\Core\Response;

beforeEach(function () {
    $this->container = new Container();
    $this->container->singleton(Request::class, function () {
        return new Request('GET', '/test', [], [], []);
    });
});

it('executes middleware in order', function () {
    $pipeline = new MiddlewarePipeline($this->container);
    $request = $this->container->resolve(Request::class);
    
    $executionOrder = [];
    
    // Add three middleware that track execution order
    $pipeline->pipe(function (Request $request, callable $next) use (&$executionOrder): Response {
        $executionOrder[] = 'first-before';
        $response = $next($request);
        $executionOrder[] = 'first-after';
        return $response;
    });
    
    $pipeline->pipe(function (Request $request, callable $next) use (&$executionOrder): Response {
        $executionOrder[] = 'second-before';
        $response = $next($request);
        $executionOrder[] = 'second-after';
        return $response;
    });
    
    $pipeline->pipe(function (Request $request, callable $next) use (&$executionOrder): Response {
        $executionOrder[] = 'third-before';
        $response = $next($request);
        $executionOrder[] = 'third-after';
        return $response;
    });
    
    // Execute pipeline with a simple destination
    $response = $pipeline->handle($request, function (Request $request) use (&$executionOrder): Response {
        $executionOrder[] = 'destination';
        return new Response('OK');
    });
    
    expect($response)->toBeInstanceOf(Response::class)
        ->and($executionOrder)->toBe([
            'first-before',
            'second-before',
            'third-before',
            'destination',
            'third-after',
            'second-after',
            'first-after'
        ]);
});

it('allows middleware to short-circuit the pipeline', function () {
    $pipeline = new MiddlewarePipeline($this->container);
    $request = $this->container->resolve(Request::class);
    
    $destinationCalled = false;
    
    // First middleware passes through
    $pipeline->pipe(function (Request $request, callable $next): Response {
        return $next($request);
    });
    
    // Second middleware short-circuits
    $pipeline->pipe(function (Request $request, callable $next): Response {
        return new Response('Short-circuited', 403);
    });
    
    // Third middleware should not be called
    $pipeline->pipe(function (Request $request, callable $next): Response {
        throw new \Exception('This should not be called');
    });
    
    $response = $pipeline->handle($request, function (Request $request) use (&$destinationCalled): Response {
        $destinationCalled = true;
        return new Response('OK');
    });
    
    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBe('Short-circuited')
        ->and($destinationCalled)->toBeFalse();
});

it('resolves middleware from container by class name', function () {
    $pipeline = new MiddlewarePipeline($this->container);
    $request = $this->container->resolve(Request::class);
    
    // Register a middleware class in the container
    $this->container->bind(TestMiddleware::class, function () {
        return new TestMiddleware();
    });
    
    // Add middleware by class name
    $pipeline->pipe(TestMiddleware::class);
    
    $response = $pipeline->handle($request, function (Request $request): Response {
        return new Response('OK');
    });
    
    expect($response->getContent())->toBe('Modified by TestMiddleware');
});

it('supports MiddlewareInterface implementations', function () {
    $pipeline = new MiddlewarePipeline($this->container);
    $request = $this->container->resolve(Request::class);
    
    $middleware = new TestMiddleware();
    $pipeline->pipe($middleware);
    
    $response = $pipeline->handle($request, function (Request $request): Response {
        return new Response('OK');
    });
    
    expect($response->getContent())->toBe('Modified by TestMiddleware');
});

it('throws exception for invalid middleware type during execution', function () {
    $pipeline = new MiddlewarePipeline($this->container);
    $request = $this->container->resolve(Request::class);
    
    // Create a mock that looks like middleware but isn't callable or MiddlewareInterface
    $invalidMiddleware = new class {
        // Not implementing MiddlewareInterface and not callable
    };
    
    // We need to bypass type checking by using reflection
    $reflection = new \ReflectionClass($pipeline);
    $property = $reflection->getProperty('middleware');
    $property->setAccessible(true);
    $property->setValue($pipeline, [$invalidMiddleware]);
    
    $pipeline->handle($request, function (Request $request): Response {
        return new Response('OK');
    });
})->throws(\InvalidArgumentException::class, 'Middleware must be callable, a class name, or implement MiddlewareInterface');

it('returns all middleware in the pipeline', function () {
    $pipeline = new MiddlewarePipeline($this->container);
    
    $middleware1 = function () {};
    $middleware2 = TestMiddleware::class;
    $middleware3 = new TestMiddleware();
    
    $pipeline->pipe($middleware1);
    $pipeline->pipe($middleware2);
    $pipeline->pipe($middleware3);
    
    $middleware = $pipeline->getMiddleware();
    
    expect($middleware)->toHaveCount(3)
        ->and($middleware[0])->toBe($middleware1)
        ->and($middleware[1])->toBe($middleware2)
        ->and($middleware[2])->toBe($middleware3);
});

// Test helper class
class TestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        return new Response('Modified by TestMiddleware');
    }
}
