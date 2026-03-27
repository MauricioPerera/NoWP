<?php

use ChimeraNoWP\Auth\AuthMiddleware;
use ChimeraNoWP\Auth\JWTManager;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;

describe('AuthMiddleware', function () {
    beforeEach(function () {
        $this->jwtManager = new JWTManager('test-secret-key', 3600);
        $this->middleware = new AuthMiddleware($this->jwtManager);
    });

    it('returns 401 when no authorization header is present', function () {
        $request = new Request('GET', '/api/protected');
        
        $next = function ($request) {
            return Response::success(['data' => 'protected']);
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe(401);
        
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('AUTHENTICATION_REQUIRED');
        expect($content['error']['message'])->toBe('Authentication required');
    });

    it('returns 401 when token is invalid', function () {
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => 'Bearer invalid-token']
        );
        
        $next = function ($request) {
            return Response::success(['data' => 'protected']);
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe(401);
        
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('INVALID_TOKEN');
    });

    it('returns 401 when token is expired', function () {
        // Create a token with -1 second TTL (already expired)
        $expiredJwtManager = new JWTManager('test-secret-key', -1);
        $token = $expiredJwtManager->generateToken(1, 'test@example.com', 'admin');
        
        // Wait a moment to ensure expiration
        sleep(1);
        
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => "Bearer {$token}"]
        );
        
        $next = function ($request) {
            return Response::success(['data' => 'protected']);
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe(401);
        
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('TOKEN_EXPIRED');
    });

    it('allows request with valid token and injects user data', function () {
        $token = $this->jwtManager->generateToken(123, 'user@example.com', 'editor');
        
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => "Bearer {$token}"]
        );
        
        $next = function ($request) {
            // Verify user data was injected
            $user = $request->getAttribute('user');
            expect($user)->not->toBeNull();
            expect($user->id)->toBe(123);
            expect($user->email)->toBe('user@example.com');
            expect($user->role->value)->toBe('editor');
            
            return Response::success(['data' => 'protected']);
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe(200);
        expect($response->isSuccessful())->toBeTrue();
    });

    it('extracts token without Bearer prefix', function () {
        $token = $this->jwtManager->generateToken(456, 'admin@example.com', 'admin');
        
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => $token] // No "Bearer " prefix
        );
        
        $next = function ($request) {
            $user = $request->getAttribute('user');
            expect($user->id)->toBe(456);
            return Response::success();
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe(200);
    });

    it('provides user data via convenience method', function () {
        $token = $this->jwtManager->generateToken(789, 'test@example.com', 'author');
        
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => "Bearer {$token}"]
        );
        
        $next = function ($request) {
            // Test convenience method
            $user = $request->user();
            expect($user)->not->toBeNull();
            expect($user->id)->toBe(789);
            expect($user->email)->toBe('test@example.com');
            expect($user->role->value)->toBe('author');
            
            return Response::success();
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->isSuccessful())->toBeTrue();
    });

    it('handles malformed authorization header gracefully', function () {
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => 'InvalidFormat']
        );
        
        $next = function ($request) {
            return Response::success();
        };

        $response = $this->middleware->handle($request, $next);

        expect($response->getStatusCode())->toBe(401);
        
        $content = json_decode($response->getContent(), true);
        expect($content['error']['code'])->toBe('INVALID_TOKEN');
    });

    it('does not call next middleware when authentication fails', function () {
        $request = new Request('GET', '/api/protected');
        
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return Response::success();
        };

        $response = $this->middleware->handle($request, $next);

        expect($nextCalled)->toBeFalse();
        expect($response->getStatusCode())->toBe(401);
    });

    it('calls next middleware when authentication succeeds', function () {
        $token = $this->jwtManager->generateToken(1, 'test@example.com', 'admin');
        
        $request = new Request(
            'GET',
            '/api/protected',
            ['Authorization' => "Bearer {$token}"]
        );
        
        $nextCalled = false;
        $next = function ($request) use (&$nextCalled) {
            $nextCalled = true;
            return Response::success();
        };

        $response = $this->middleware->handle($request, $next);

        expect($nextCalled)->toBeTrue();
        expect($response->isSuccessful())->toBeTrue();
    });
});
