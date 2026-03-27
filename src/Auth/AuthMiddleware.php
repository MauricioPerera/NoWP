<?php

namespace ChimeraNoWP\Auth;

use ChimeraNoWP\Core\MiddlewareInterface;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use Exception;

/**
 * AuthMiddleware - Validates JWT tokens in requests
 * 
 * Requirements: 3.3 - Validate JWT tokens and inject user data
 * 
 * This middleware:
 * - Extracts JWT token from Authorization header
 * - Validates the token using JWTManager
 * - Returns 401 if token is invalid or expired
 * - Injects user data into Request if token is valid
 */
class AuthMiddleware implements MiddlewareInterface
{
    private JWTManager $jwtManager;

    /**
     * Create a new AuthMiddleware instance
     * 
     * @param JWTManager $jwtManager
     */
    public function __construct(JWTManager $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }

    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Extract token from Authorization header
        $token = $this->extractToken($request);

        // If no token provided, return 401
        if ($token === null) {
            return Response::error(
                'Authentication required',
                'AUTHENTICATION_REQUIRED',
                401
            );
        }

        // Validate and parse token
        try {
            $payload = $this->jwtManager->parseToken($token);

            // Inject user data into request
            $this->injectUserData($request, $payload);

            // Continue to next middleware
            return $next($request);

        } catch (Exception $e) {
            // Check if the error is due to expiration
            if (str_contains($e->getMessage(), 'Expired token')) {
                return Response::error(
                    'Token has expired',
                    'TOKEN_EXPIRED',
                    401
                );
            }

            // Token is invalid for other reasons
            return Response::error(
                'Invalid authentication token',
                'INVALID_TOKEN',
                401
            );
        }
    }

    /**
     * Extract JWT token from Authorization header
     * 
     * Supports both "Bearer <token>" and "<token>" formats
     * 
     * @param Request $request
     * @return string|null
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->getHeader('Authorization');

        if ($authHeader === null) {
            return null;
        }

        // Remove "Bearer " prefix if present
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Return token as-is if no Bearer prefix
        return $authHeader;
    }

    /**
     * Inject user data into the request
     * 
     * Stores the decoded JWT payload in the request for later access
     * 
     * @param Request $request
     * @param array $payload
     * @return void
     */
    private function injectUserData(Request $request, array $payload): void
    {
        // Store a proper User object in request attributes
        // This will be accessible via $request->getAttribute('user')
        $user = new User(
            id: (int) ($payload['sub'] ?? 0),
            email: $payload['email'] ?? '',
            passwordHash: '',
            displayName: $payload['name'] ?? $payload['email'] ?? '',
            role: UserRole::tryFrom($payload['role'] ?? '') ?? UserRole::SUBSCRIBER,
        );
        $request->setAttribute('user', $user);
    }
}
