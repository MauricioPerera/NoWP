<?php

use ChimeraNoWP\Auth\JWTManager;

describe('JWTManager', function () {
    beforeEach(function () {
        $this->secret = 'test-secret-key-for-jwt-testing';
        $this->jwtManager = new JWTManager($this->secret, 3600);
    });

    it('generates a valid JWT token', function () {
        $token = $this->jwtManager->generateToken(1, 'user@example.com', 'admin');
        
        expect($token)->toBeString()
            ->and($token)->not->toBeEmpty();
    });

    it('includes all required claims in generated token', function () {
        $userId = 42;
        $email = 'test@example.com';
        $role = 'editor';
        
        $token = $this->jwtManager->generateToken($userId, $email, $role);
        $payload = $this->jwtManager->parseToken($token);
        
        expect($payload)->toHaveKey('sub')
            ->and($payload['sub'])->toBe($userId)
            ->and($payload)->toHaveKey('email')
            ->and($payload['email'])->toBe($email)
            ->and($payload)->toHaveKey('role')
            ->and($payload['role'])->toBe($role)
            ->and($payload)->toHaveKey('iat')
            ->and($payload)->toHaveKey('exp');
    });

    it('parses a valid token correctly', function () {
        $token = $this->jwtManager->generateToken(1, 'user@example.com', 'admin');
        $payload = $this->jwtManager->parseToken($token);
        
        expect($payload)->toBeArray()
            ->and($payload['sub'])->toBe(1)
            ->and($payload['email'])->toBe('user@example.com')
            ->and($payload['role'])->toBe('admin');
    });

    it('throws exception for invalid token', function () {
        $this->jwtManager->parseToken('invalid.token.here');
    })->throws(Exception::class, 'Invalid or expired token');

    it('throws exception for token with wrong signature', function () {
        $wrongManager = new JWTManager('different-secret', 3600);
        $token = $wrongManager->generateToken(1, 'user@example.com', 'admin');
        
        $this->jwtManager->parseToken($token);
    })->throws(Exception::class);

    it('detects non-expired token correctly', function () {
        $token = $this->jwtManager->generateToken(1, 'user@example.com', 'admin');
        
        expect($this->jwtManager->isExpired($token))->toBeFalse();
    });

    it('detects expired token correctly', function () {
        // Create a token with 1 second TTL
        $shortLivedManager = new JWTManager($this->secret, 1);
        $token = $shortLivedManager->generateToken(1, 'user@example.com', 'admin');
        
        // Wait for token to expire
        sleep(2);
        
        expect($shortLivedManager->isExpired($token))->toBeTrue();
    });

    it('considers invalid token as expired', function () {
        expect($this->jwtManager->isExpired('invalid.token.here'))->toBeTrue();
    });

    it('throws exception when secret is empty', function () {
        new JWTManager('', 3600);
    })->throws(Exception::class, 'JWT secret cannot be empty');

    it('sets expiration time based on TTL', function () {
        $ttl = 7200; // 2 hours
        $manager = new JWTManager($this->secret, $ttl);
        
        $beforeGeneration = time();
        $token = $manager->generateToken(1, 'user@example.com', 'admin');
        $afterGeneration = time();
        
        $payload = $manager->parseToken($token);
        
        expect($payload['exp'])->toBeGreaterThanOrEqual($beforeGeneration + $ttl)
            ->and($payload['exp'])->toBeLessThanOrEqual($afterGeneration + $ttl);
    });

    it('sets issued at time correctly', function () {
        $beforeGeneration = time();
        $token = $this->jwtManager->generateToken(1, 'user@example.com', 'admin');
        $afterGeneration = time();
        
        $payload = $this->jwtManager->parseToken($token);
        
        expect($payload['iat'])->toBeGreaterThanOrEqual($beforeGeneration)
            ->and($payload['iat'])->toBeLessThanOrEqual($afterGeneration);
    });

    it('handles different user roles', function () {
        $roles = ['admin', 'editor', 'author', 'subscriber'];
        
        foreach ($roles as $role) {
            $token = $this->jwtManager->generateToken(1, 'user@example.com', $role);
            $payload = $this->jwtManager->parseToken($token);
            
            expect($payload['role'])->toBe($role);
        }
    });

    it('generates different tokens for different users', function () {
        $token1 = $this->jwtManager->generateToken(1, 'user1@example.com', 'admin');
        $token2 = $this->jwtManager->generateToken(2, 'user2@example.com', 'editor');
        
        expect($token1)->not->toBe($token2);
        
        $payload1 = $this->jwtManager->parseToken($token1);
        $payload2 = $this->jwtManager->parseToken($token2);
        
        expect($payload1['sub'])->toBe(1)
            ->and($payload2['sub'])->toBe(2)
            ->and($payload1['email'])->toBe('user1@example.com')
            ->and($payload2['email'])->toBe('user2@example.com');
    });
});
