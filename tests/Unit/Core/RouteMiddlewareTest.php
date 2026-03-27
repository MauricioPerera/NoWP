<?php

use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\MiddlewareInterface;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Route;

beforeEach(function () {
    $this->container = new Container();
    $this->container->singleton(Request::class, function () {
        return new Request('GET', '/test', [], [], []);
    });
});

it('executes route middleware before handler', function () {
    $executionOrder = [];
    
    $middleware = function (Request $request, callable $next) use (&$executionOrder): Response {
        $executionOrder[] = 'middleware';
        return $next($request);
    };
    
    $handler = function () use (&$executionOrder): Response {
        $executionOrder[] = 'handler';
        return new Response('OK');
    };
    
    $route = new Route('GET', '/test', $handler);
    $route->middleware($middleware);
    
    $response = $route->execute($this->container);
    
    expect($response)->toBeInstanceOf(Response::class)
        ->and($executionOrder)->toBe(['middleware', 'handler']);
});

it('executes multiple middleware in order', function () {
    $executionOrder = [];
    
    $middleware1 = function (Request $request, callable $next) use (&$executionOrder): Response {
        $executionOrder[] = 'middleware1';
        return $next($request);
    };
    
    $middleware2 = function (Request $request, callable $next) use (&$executionOrder): Response {
        $executionOrder[] = 'middleware2';
        return $next($request);
    };
    
    $handler = function () use (&$executionOrder): Response {
        $executionOrder[] = 'handler';
        return new Response('OK');
    };
    
    $route = new Route('GET', '/test', $handler);
    $route->middleware($middleware1)->middleware($middleware2);
    
    $response = $route->execute($this->container);
    
    expect($executionOrder)->toBe(['middleware1', 'middleware2', 'handler']);
});

it('allows middleware to modify response', function () {
    $middleware = function (Request $request, callable $next): Response {
        $response = $next($request);
        return new Response('Modified: ' . $response->getContent());
    };
    
    $handler = function (): Response {
        return new Response('Original');
    };
    
    $route = new Route('GET', '/test', $handler);
    $route->middleware($middleware);
    
    $response = $route->execute($this->container);
    
    expect($response->getContent())->toBe('Modified: Original');
});

it('allows middleware to short-circuit and return early', function () {
    $handlerCalled = false;
    
    $middleware = function (Request $request, callable $next): Response {
        // Don't call next, return immediately
        return new Response('Unauthorized', 401);
    };
    
    $handler = function () use (&$handlerCalled): Response {
        $handlerCalled = true;
        return new Response('OK');
    };
    
    $route = new Route('GET', '/test', $handler);
    $route->middleware($middleware);
    
    $response = $route->execute($this->container);
    
    expect($response->getStatusCode())->toBe(401)
        ->and($response->getContent())->toBe('Unauthorized')
        ->and($handlerCalled)->toBeFalse();
});

it('supports MiddlewareInterface implementations', function () {
    $middleware = new class implements MiddlewareInterface {
        public function handle(Request $request, callable $next): Response
        {
            $response = $next($request);
            return new Response('Wrapped: ' . $response->getContent());
        }
    };
    
    $handler = function (): Response {
        return new Response('Content');
    };
    
    $route = new Route('GET', '/test', $handler);
    $route->middleware($middleware);
    
    $response = $route->execute($this->container);
    
    expect($response->getContent())->toBe('Wrapped: Content');
});

it('resolves middleware from container by class name', function () {
    $this->container->bind(AuthMiddleware::class, function () {
        return new AuthMiddleware();
    });
    
    $handler = function (): Response {
        return new Response('Protected Content');
    };
    
    $route = new Route('GET', '/test', $handler);
    $route->middleware(AuthMiddleware::class);
    
    $response = $route->execute($this->container);
    
    expect($response->getContent())->toBe('Auth: Protected Content');
});

it('returns all middleware for a route', function () {
    $middleware1 = function () {};
    $middleware2 = function () {};
    
    $route = new Route('GET', '/test', function () {});
    $route->middleware($middleware1)->middleware($middleware2);
    
    $middleware = $route->getMiddleware();
    
    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBe($middleware1)
        ->and($middleware[1])->toBe($middleware2);
});

// Test helper class
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        return new Response('Auth: ' . $response->getContent());
    }
}
