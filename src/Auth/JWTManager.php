<?php

namespace ChimeraNoWP\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * JWTManager - Handles JWT token generation and validation
 * 
 * Requirements: 3.1, 3.2 - JWT authentication with configurable expiration
 */
class JWTManager
{
    private string $secret;
    private int $ttl;
    private string $algorithm;

    /**
     * @param string $secret Secret key for signing tokens
     * @param int $ttl Time to live in seconds (default: 3600 = 1 hour)
     * @param string $algorithm Algorithm for signing (default: HS256)
     */
    public function __construct(string $secret, int $ttl = 3600, string $algorithm = 'HS256')
    {
        if (empty($secret)) {
            throw new Exception('JWT secret cannot be empty');
        }
        
        $this->secret = $secret;
        $this->ttl = $ttl;
        $this->algorithm = $algorithm;
    }

    /**
     * Generate a JWT token for a user
     *
     * @param int $userId User ID (sub claim)
     * @param string $email User email
     * @param string $role User role
     * @return string The generated JWT token
     */
    public function generateToken(int $userId, string $email, string $role): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->ttl;

        $payload = [
            'sub' => $userId,
            'email' => $email,
            'role' => $role,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Parse and validate a JWT token
     *
     * @param string $token The JWT token to parse
     * @return array The decoded token payload as an associative array
     * @throws Exception If token is invalid or expired
     */
    public function parseToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            throw new Exception('Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Check if a token is expired
     *
     * @deprecated JWT::decode() already validates expiration
     * @param string $token The JWT token to check
     * @return bool True if the token is expired, false otherwise
     */
    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->parseToken($token);
            
            if (!isset($payload['exp'])) {
                return true;
            }
            
            return $payload['exp'] < time();
        } catch (Exception $e) {
            // If we can't parse the token, consider it expired
            return true;
        }
    }
}
