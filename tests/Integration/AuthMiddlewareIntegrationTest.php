<?php

use Framework\Auth\AuthMiddleware;
use Framework\Auth\JWTManager;
use Framework\Core\Container;
use Framework\Core\MiddlewarePipeline;
use Framework\Core\Request;
use Framework\Core\Response;

describe('AuthMiddleware Integration', function () {
    beforeEach(function () {
        $this->container = new Container();
        $this->jwtManager = new JWTManager('integration-test-secret', 3600);
        $this->container->singleton(JWTManager::class, fn() => $this->jwtManager);
    });

    it('protects routes with authentication middleware', function () {
        $pipeline = new MiddlewarePipeline($this->container);
        $pipeline->pipe(new AuthMiddleware($this->jwtManager));

        // Request without token
        $request = new Request('GET', '/api/users');
        
        $destination = function ($request) {
            return Response::success(['users' => []]);
        };

        $response = $pipeline->handle($request, $destination);

        expect($response->getStatusCode())->toBe(401);
    });

    it('allows authenticated requests through pipeline', function () {
        $token = $this->jwtManager->generateToken(1, 'admin@example.com', 'admin');
        
        $pipeline = new MiddlewarePipeline($this->container);
        $pipeline->pipe(new AuthMiddleware($this->jwtManager));

        $request = new Request(
            'GET',
            '/api/users',
            ['Authorization' => "Bearer {$token}"]
        );
        
        $destination = function ($request) {
            // Access user data in the handler
            $user = $request->user();
            return Response::success([
                'message' => "Hello {$user['email']}",
                'role' => $user['role']
            ]);
        };

        $response = $pipeline->handle($request, $destination);

        expect($response->getStatusCode())->toBe(200);
        
        $content = json_decode($response->getContent(), true);
        expect($content['data']['message'])->toBe('Hello admin@example.com');
        expect($content['data']['role'])->toBe('admin');
    });

    it('chains multiple middleware correctly', function () {
        $token = $this->jwtManager->generateToken(2, 'user@example.com', 'editor');
        
        $pipeline = new MiddlewarePipeline($this->container);
        
        // Add a logging middleware before auth
        $pipeline->pipe(function ($request, $next) {
            // Simulate logging
            $request->setAttribute('logged', true);
            return $next($request);
        });
        
        // Add auth middleware
        $pipeline->pipe(new AuthMiddleware($this->jwtManager));
        
        // Add a role check middleware after auth
        $pipeline->pipe(function ($request, $next) {
            $user = $request->user();
            if ($user['role'] !== 'editor') {
                return Response::error('Forbidden', 'FORBIDDEN', 403);
            }
            return $next($request);
        });

        $request = new Request(
            'POST',
            '/api/content',
            ['Authorization' => "Bearer {$token}"]
        );
        
        $destination = function ($request) {
            return Response::success([
                'logged' => $request->getAttribute('logged'),
                'user_id' => $request->user()['id']
            ]);
        };

        $response = $pipeline->handle($request, $destination);

        expect($response->getStatusCode())->toBe(200);
        
        $content = json_decode($response->getContent(), true);
        expect($content['data']['logged'])->toBeTrue();
        expect($content['data']['user_id'])->toBe(2);
    });

    it('stops pipeline when auth fails', function () {
        $pipeline = new MiddlewarePipeline($this->container);
        
        $pipeline->pipe(new AuthMiddleware($this->jwtManager));
        
        // This middleware should never execute
        $afterAuthCalled = false;
        $pipeline->pipe(function ($request, $next) use (&$afterAuthCalled) {
            $afterAuthCalled = true;
            return $next($request);
        });

        $request = new Request('GET', '/api/protected');
        
        $destination = function ($request) {
            return Response::success();
        };

        $response = $pipeline->handle($request, $destination);

        expect($response->getStatusCode())->toBe(401);
        expect($afterAuthCalled)->toBeFalse();
    });
});
