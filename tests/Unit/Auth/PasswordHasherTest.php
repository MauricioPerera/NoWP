<?php

use Framework\Auth\PasswordHasher;

describe('PasswordHasher', function () {
    beforeEach(function () {
        $this->hasher = new PasswordHasher();
    });

    it('hashes a password', function () {
        $password = 'my-secure-password';
        $hash = $this->hasher->hash($password);

        expect($hash)->toBeString()
            ->and($hash)->not->toBe($password)
            ->and(strlen($hash))->toBeGreaterThan(50);
    });

    it('verifies a correct password', function () {
        $password = 'my-secure-password';
        $hash = $this->hasher->hash($password);

        $result = $this->hasher->verify($password, $hash);

        expect($result)->toBeTrue();
    });

    it('rejects an incorrect password', function () {
        $password = 'my-secure-password';
        $wrongPassword = 'wrong-password';
        $hash = $this->hasher->hash($password);

        $result = $this->hasher->verify($wrongPassword, $hash);

        expect($result)->toBeFalse();
    });

    it('generates different hashes for the same password', function () {
        $password = 'my-secure-password';
        $hash1 = $this->hasher->hash($password);
        $hash2 = $this->hasher->hash($password);

        expect($hash1)->not->toBe($hash2)
            ->and($this->hasher->verify($password, $hash1))->toBeTrue()
            ->and($this->hasher->verify($password, $hash2))->toBeTrue();
    });

    it('uses bcrypt algorithm', function () {
        $password = 'test-password';
        $hash = $this->hasher->hash($password);

        // Bcrypt hashes start with $2y$ (PHP's bcrypt identifier)
        expect($hash)->toStartWith('$2y$');
    });

    it('uses cost factor of 10', function () {
        $password = 'test-password';
        $hash = $this->hasher->hash($password);

        // Bcrypt format: $2y$10$... where 10 is the cost
        expect($hash)->toMatch('/^\$2y\$10\$/');
    });

    it('handles empty password', function () {
        $password = '';
        $hash = $this->hasher->hash($password);

        expect($this->hasher->verify('', $hash))->toBeTrue()
            ->and($this->hasher->verify('not-empty', $hash))->toBeFalse();
    });

    it('handles special characters in password', function () {
        $password = '!@#$%^&*()_+-=[]{}|;:,.<>?/~`';
        $hash = $this->hasher->hash($password);

        expect($this->hasher->verify($password, $hash))->toBeTrue();
    });

    it('handles unicode characters in password', function () {
        $password = 'contraseña-密码-пароль-🔒';
        $hash = $this->hasher->hash($password);

        expect($this->hasher->verify($password, $hash))->toBeTrue();
    });

    it('handles very long passwords', function () {
        $password = str_repeat('a', 1000);
        $hash = $this->hasher->hash($password);

        expect($this->hasher->verify($password, $hash))->toBeTrue();
    });

    it('detects when hash needs rehashing', function () {
        $password = 'test-password';
        
        // Create a hash with a different cost factor
        $oldHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 8]);

        expect($this->hasher->needsRehash($oldHash))->toBeTrue();
    });

    it('detects when hash does not need rehashing', function () {
        $password = 'test-password';
        $hash = $this->hasher->hash($password);

        expect($this->hasher->needsRehash($hash))->toBeFalse();
    });
});
