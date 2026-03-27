<?php

namespace ChimeraNoWP\Auth;

/**
 * PasswordHasher - Handles password hashing and verification using bcrypt
 * 
 * Requirements: 3.6 - Hash passwords using bcrypt with work factor minimum of 10
 */
class PasswordHasher
{
    private const ALGORITHM = PASSWORD_BCRYPT;
    private const COST = 10;

    /**
     * Hash a plain text password using bcrypt
     *
     * @param string $password The plain text password to hash
     * @return string The hashed password
     */
    public function hash(string $password): string
    {
        return password_hash($password, self::ALGORITHM, [
            'cost' => self::COST
        ]);
    }

    /**
     * Verify a plain text password against a hash
     *
     * @param string $password The plain text password to verify
     * @param string $hash The hash to verify against
     * @return bool True if the password matches the hash, false otherwise
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed (e.g., if cost factor changed)
     *
     * @param string $hash The hash to check
     * @return bool True if the hash needs to be rehashed
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGORITHM, [
            'cost' => self::COST
        ]);
    }
}
