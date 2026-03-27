<?php

use ChimeraNoWP\Core\Router;
use ChimeraNoWP\Core\Route;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;

describe('Router', function () {
    
    it('registers GET routes', function () {
        $router = new Router();
        $route = $router->get('/test', fn() => 'test');
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getMethod())->toBe('GET')
            ->and($route->getPath())->toBe('/test');
    });

    it('registers POST routes', function () {
        $router = new Router();
        $route = $router->post('/test', fn() => 'test');
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getMethod())->toBe('POST')
            ->and($route->getPath())->toBe('/test');
    });

    it('registers PUT routes', function () {
        $router = new Router();
        $route = $router->put('/test', fn() => 'test');
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getMethod())->toBe('PUT')
            ->and($route->getPath())->toBe('/test');
    });

    it('registers DELETE routes', function () {
        $router = new Router();
        $route = $router->delete('/test', fn() => 'test');
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getMethod())->toBe('DELETE')
            ->and($route->getPath())->toBe('/test');
    });

    it('registers PATCH routes', function () {
        $router = new Router();
        $route = $router->patch('/test', fn() => 'test');
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getMethod())->toBe('PATCH')
            ->and($route->getPath())->toBe('/test');
    });

    it('matches simple routes', function () {
        $router = new Router();
        $router->get('/users', fn() => 'users');
        
        $request = new Request('GET', '/users');
        $route = $router->match($request);
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getPath())->toBe('/users');
    });

    it('matches routes with dynamic parameters', function () {
        $router = new Router();
        $router->get('/users/{id}', fn($id) => "user $id");
        
        $request = new Request('GET', '/users/123');
        $route = $router->match($request);
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getParameters())->toHaveKey('id')
            ->and($route->getParameter('id'))->toBe('123');
    });

    it('matches routes with multiple parameters', function () {
        $router = new Router();
        $router->get('/posts/{postId}/comments/{commentId}', fn($postId, $commentId) => "post $postId comment $commentId");
        
        $request = new Request('GET', '/posts/456/comments/789');
        $route = $router->match($request);
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getParameters())->toHaveKeys(['postId', 'commentId'])
            ->and($route->getParameter('postId'))->toBe('456')
            ->and($route->getParameter('commentId'))->toBe('789');
    });

    it('returns null when no route matches', function () {
        $router = new Router();
        $router->get('/users', fn() => 'users');
        
        $request = new Request('GET', '/posts');
        $route = $router->match($request);
        
        expect($route)->toBeNull();
    });

    it('distinguishes between different HTTP methods', function () {
        $router = new Router();
        $router->get('/resource', fn() => 'get');
        $router->post('/resource', fn() => 'post');
        
        $getRequest = new Request('GET', '/resource');
        $postRequest = new Request('POST', '/resource');
        
        $getRoute = $router->match($getRequest);
        $postRoute = $router->match($postRequest);
        
        expect($getRoute)->toBeInstanceOf(Route::class)
            ->and($getRoute->getMethod())->toBe('GET')
            ->and($postRoute)->toBeInstanceOf(Route::class)
            ->and($postRoute->getMethod())->toBe('POST');
    });

    it('creates route groups with prefix', function () {
        $router = new Router();
        
        $router->group(['prefix' => 'api'], function ($router) {
            $router->get('/users', fn() => 'users');
            $router->get('/posts', fn() => 'posts');
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(2)
            ->and($routes[0]->getPath())->toBe('/api/users')
            ->and($routes[1]->getPath())->toBe('/api/posts');
    });

    it('creates nested route groups with combined prefixes', function () {
        $router = new Router();
        
        $router->group(['prefix' => 'api'], function ($router) {
            $router->group(['prefix' => 'v1'], function ($router) {
                $router->get('/users', fn() => 'users');
            });
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(1)
            ->and($routes[0]->getPath())->toBe('/api/v1/users');
    });

    it('applies group middleware to routes', function () {
        $router = new Router();
        
        $middleware = fn($request) => null;
        
        $router->group(['middleware' => $middleware], function ($router) {
            $router->get('/protected', fn() => 'protected');
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(1)
            ->and($routes[0]->getMiddleware())->toContain($middleware);
    });

    it('applies multiple middleware from group', function () {
        $router = new Router();
        
        $middleware1 = fn($request) => null;
        $middleware2 = fn($request) => null;
        
        $router->group(['middleware' => [$middleware1, $middleware2]], function ($router) {
            $router->get('/protected', fn() => 'protected');
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(1)
            ->and($routes[0]->getMiddleware())->toHaveCount(2)
            ->and($routes[0]->getMiddleware())->toContain($middleware1)
            ->and($routes[0]->getMiddleware())->toContain($middleware2);
    });

    it('combines prefix and middleware in groups', function () {
        $router = new Router();
        
        $middleware = fn($request) => null;
        
        $router->group(['prefix' => 'api', 'middleware' => $middleware], function ($router) {
            $router->get('/users', fn() => 'users');
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(1)
            ->and($routes[0]->getPath())->toBe('/api/users')
            ->and($routes[0]->getMiddleware())->toContain($middleware);
    });

    it('handles nested groups with different middleware', function () {
        $router = new Router();
        
        $middleware1 = fn($request) => 'auth';
        $middleware2 = fn($request) => 'admin';
        
        $router->group(['prefix' => 'api', 'middleware' => $middleware1], function ($router) use ($middleware2) {
            $router->get('/public', fn() => 'public');
            
            $router->group(['prefix' => 'admin', 'middleware' => $middleware2], function ($router) {
                $router->get('/users', fn() => 'admin users');
            });
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(2)
            ->and($routes[0]->getPath())->toBe('/api/public')
            ->and($routes[0]->getMiddleware())->toHaveCount(1)
            ->and($routes[1]->getPath())->toBe('/api/admin/users')
            ->and($routes[1]->getMiddleware())->toHaveCount(2);
    });

    it('matches grouped routes correctly', function () {
        $router = new Router();
        
        $router->group(['prefix' => 'api'], function ($router) {
            $router->get('/contents/{id}', fn($id) => "content $id");
        });
        
        $request = new Request('GET', '/api/contents/123');
        $route = $router->match($request);
        
        expect($route)->toBeInstanceOf(Route::class)
            ->and($route->getParameter('id'))->toBe('123');
    });

    it('handles routes with leading and trailing slashes in groups', function () {
        $router = new Router();
        
        $router->group(['prefix' => '/api/'], function ($router) {
            $router->get('/users/', fn() => 'users');
        });
        
        $routes = $router->getRoutes();
        
        expect($routes[0]->getPath())->toBe('/api/users');
    });

    it('allows individual route middleware in addition to group middleware', function () {
        $router = new Router();
        
        $groupMiddleware = fn($request) => 'group';
        $routeMiddleware = fn($request) => 'route';
        
        $router->group(['middleware' => $groupMiddleware], function ($router) use ($routeMiddleware) {
            $router->get('/test', fn() => 'test')->middleware($routeMiddleware);
        });
        
        $routes = $router->getRoutes();
        
        expect($routes[0]->getMiddleware())->toHaveCount(2)
            ->and($routes[0]->getMiddleware())->toContain($groupMiddleware)
            ->and($routes[0]->getMiddleware())->toContain($routeMiddleware);
    });

    it('returns all registered routes', function () {
        $router = new Router();
        
        $router->get('/users', fn() => 'users');
        $router->post('/users', fn() => 'create user');
        $router->get('/posts', fn() => 'posts');
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(3)
            ->and($routes)->each->toBeInstanceOf(Route::class);
    });
});
