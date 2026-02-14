<?php

use Framework\Auth\PermissionMiddleware;
use Framework\Auth\User;
use Framework\Auth\UserRole;
use Framework\Core\Request;
use Framework\Core\Response;

describe('PermissionMiddleware', function () {
    it('allows request when user has required permission', function () {
        $user = new User(1, 'admin@example.com', 'hash', 'Admin', UserRole::ADMIN);
        
        $request = new Request('GET', '/api/users', [], [], [], []);
        $request->setAttribute('user', $user);

        $middleware = new PermissionMiddleware('user.read');
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($nextCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(200);
    });

    it('returns 403 when user lacks required permission', function () {
        $subscriber = new User(1, 'subscriber@example.com', 'hash', 'Subscriber', UserRole::SUBSCRIBER);
        
        $request = new Request('POST', '/api/content', [], [], [], []);
        $request->setAttribute('user', $subscriber);

        $middleware = new PermissionMiddleware('content.create');
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($nextCalled)->toBeFalse()
            ->and($response->getStatusCode())->toBe(403);

        $body = json_decode($response->getContent(), true);
        expect($body['error']['code'])->toBe('INSUFFICIENT_PERMISSIONS')
            ->and($body['error']['details']['required_permission'])->toBe('content.create')
            ->and($body['error']['details']['user_role'])->toBe('subscriber');
    });

    it('returns 401 when no user is authenticated', function () {
        $request = new Request('GET', '/api/users', [], [], [], []);
        // No user attribute set

        $middleware = new PermissionMiddleware('user.read');
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($nextCalled)->toBeFalse()
            ->and($response->getStatusCode())->toBe(401);

        $body = json_decode($response->getContent(), true);
        expect($body['error']['code'])->toBe('AUTHENTICATION_REQUIRED');
    });

    it('checks multiple permissions', function () {
        $editor = new User(1, 'editor@example.com', 'hash', 'Editor', UserRole::EDITOR);
        
        $request = new Request('POST', '/api/content', [], [], [], []);
        $request->setAttribute('user', $editor);

        // Editor has both permissions
        $middleware = new PermissionMiddleware(['content.create', 'content.publish']);
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($nextCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(200);
    });

    it('fails when user lacks one of multiple required permissions', function () {
        $author = new User(1, 'author@example.com', 'hash', 'Author', UserRole::AUTHOR);
        
        $request = new Request('DELETE', '/api/content/1', [], [], [], []);
        $request->setAttribute('user', $author);

        // Author has content.read but not content.delete
        $middleware = new PermissionMiddleware(['content.read', 'content.delete']);
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($nextCalled)->toBeFalse()
            ->and($response->getStatusCode())->toBe(403);

        $body = json_decode($response->getContent(), true);
        expect($body['error']['details']['required_permission'])->toBe('content.delete');
    });

    it('works with single permission as string', function () {
        $admin = new User(1, 'admin@example.com', 'hash', 'Admin', UserRole::ADMIN);
        
        $request = new Request('GET', '/api/settings', [], [], [], []);
        $request->setAttribute('user', $admin);

        $middleware = new PermissionMiddleware('settings.manage');
        
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;
            return new Response('Success', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($nextCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(200);
    });
});
