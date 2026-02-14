<?php

use Framework\Core\Router;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Core\Container;

describe('Router Integration', function () {
    
    it('handles RESTful routes for a resource', function () {
        $router = new Router();
        
        // Register RESTful routes for contents
        $router->get('/api/contents', fn() => Response::json(['contents' => []]));
        $router->get('/api/contents/{id}', fn($id) => Response::json(['id' => $id]));
        $router->post('/api/contents', fn() => Response::json(['created' => true]));
        $router->put('/api/contents/{id}', fn($id) => Response::json(['updated' => $id]));
        $router->delete('/api/contents/{id}', fn($id) => Response::json(['deleted' => $id]));
        
        // Test GET all
        $request = new Request('GET', '/api/contents');
        $route = $router->match($request);
        expect($route)->not->toBeNull();
        
        // Test GET one with parameter
        $request = new Request('GET', '/api/contents/123');
        $route = $router->match($request);
        expect($route)->not->toBeNull()
            ->and($route->getParameter('id'))->toBe('123');
        
        // Test POST
        $request = new Request('POST', '/api/contents');
        $route = $router->match($request);
        expect($route)->not->toBeNull();
        
        // Test PUT with parameter
        $request = new Request('PUT', '/api/contents/456');
        $route = $router->match($request);
        expect($route)->not->toBeNull()
            ->and($route->getParameter('id'))->toBe('456');
        
        // Test DELETE with parameter
        $request = new Request('DELETE', '/api/contents/789');
        $route = $router->match($request);
        expect($route)->not->toBeNull()
            ->and($route->getParameter('id'))->toBe('789');
    });

    it('handles grouped API routes with prefix and middleware', function () {
        $router = new Router();
        
        $authMiddleware = fn($request) => null;
        
        $router->group(['prefix' => 'api', 'middleware' => $authMiddleware], function ($router) {
            $router->get('/contents', fn() => Response::json(['contents' => []]));
            $router->get('/contents/{id}', fn($id) => Response::json(['id' => $id]));
            $router->post('/contents', fn() => Response::json(['created' => true]));
            $router->put('/contents/{id}', fn($id) => Response::json(['updated' => $id]));
            $router->delete('/contents/{id}', fn($id) => Response::json(['deleted' => $id]));
        });
        
        $routes = $router->getRoutes();
        
        // Verify all routes have the prefix
        expect($routes)->toHaveCount(5)
            ->and($routes[0]->getPath())->toBe('/api/contents')
            ->and($routes[1]->getPath())->toBe('/api/contents/{id}')
            ->and($routes[2]->getPath())->toBe('/api/contents')
            ->and($routes[3]->getPath())->toBe('/api/contents/{id}')
            ->and($routes[4]->getPath())->toBe('/api/contents/{id}');
        
        // Verify all routes have the middleware
        foreach ($routes as $route) {
            expect($route->getMiddleware())->toContain($authMiddleware);
        }
    });

    it('handles nested groups with combined prefixes and middleware', function () {
        $router = new Router();
        
        $authMiddleware = fn($request) => 'auth';
        $adminMiddleware = fn($request) => 'admin';
        
        $router->group(['prefix' => 'api', 'middleware' => $authMiddleware], function ($router) use ($adminMiddleware) {
            // Public API routes
            $router->get('/contents', fn() => Response::json(['contents' => []]));
            
            // Admin routes with additional middleware
            $router->group(['prefix' => 'admin', 'middleware' => $adminMiddleware], function ($router) {
                $router->get('/users', fn() => Response::json(['users' => []]));
                $router->delete('/users/{id}', fn($id) => Response::json(['deleted' => $id]));
            });
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(3)
            // Public route has only auth middleware
            ->and($routes[0]->getPath())->toBe('/api/contents')
            ->and($routes[0]->getMiddleware())->toHaveCount(1)
            // Admin routes have both auth and admin middleware
            ->and($routes[1]->getPath())->toBe('/api/admin/users')
            ->and($routes[1]->getMiddleware())->toHaveCount(2)
            ->and($routes[2]->getPath())->toBe('/api/admin/users/{id}')
            ->and($routes[2]->getMiddleware())->toHaveCount(2);
    });

    it('matches complex routes with multiple parameters', function () {
        $router = new Router();
        
        $router->group(['prefix' => 'api'], function ($router) {
            $router->get('/posts/{postId}/comments/{commentId}', 
                fn($postId, $commentId) => Response::json([
                    'post' => $postId,
                    'comment' => $commentId
                ])
            );
        });
        
        $request = new Request('GET', '/api/posts/42/comments/99');
        $route = $router->match($request);
        
        expect($route)->not->toBeNull()
            ->and($route->getParameter('postId'))->toBe('42')
            ->and($route->getParameter('commentId'))->toBe('99');
    });

    it('demonstrates complete RESTful API structure', function () {
        $router = new Router();
        
        // API v1 routes
        $router->group(['prefix' => 'api/v1'], function ($router) {
            // Contents resource
            $router->get('/contents', fn() => 'list contents');
            $router->post('/contents', fn() => 'create content');
            $router->get('/contents/{id}', fn($id) => "get content $id");
            $router->put('/contents/{id}', fn($id) => "update content $id");
            $router->delete('/contents/{id}', fn($id) => "delete content $id");
            
            // Nested comments resource
            $router->get('/contents/{contentId}/comments', fn($contentId) => "list comments for $contentId");
            $router->post('/contents/{contentId}/comments', fn($contentId) => "create comment for $contentId");
        });
        
        $routes = $router->getRoutes();
        
        expect($routes)->toHaveCount(7);
        
        // Test matching various routes
        $tests = [
            ['GET', '/api/v1/contents', null],
            ['POST', '/api/v1/contents', null],
            ['GET', '/api/v1/contents/123', ['id' => '123']],
            ['PUT', '/api/v1/contents/456', ['id' => '456']],
            ['DELETE', '/api/v1/contents/789', ['id' => '789']],
            ['GET', '/api/v1/contents/100/comments', ['contentId' => '100']],
            ['POST', '/api/v1/contents/200/comments', ['contentId' => '200']],
        ];
        
        foreach ($tests as [$method, $path, $expectedParams]) {
            $request = new Request($method, $path);
            $route = $router->match($request);
            
            expect($route)->not->toBeNull();
            
            if ($expectedParams) {
                foreach ($expectedParams as $key => $value) {
                    expect($route->getParameter($key))->toBe($value);
                }
            }
        }
    });
});
