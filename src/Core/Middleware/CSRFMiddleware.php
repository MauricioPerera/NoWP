<?php

/**
 * CSRF Middleware
 * 
 * Protects against Cross-Site Request Forgery attacks.
 * 
 * Requirements: 12.2
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Middleware;

use ChimeraNoWP\Core\MiddlewareInterface;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Exceptions\AuthorizationException;

class CSRFMiddleware implements MiddlewareInterface
{
    private string $tokenKey = 'csrf_token';
    private string $headerName = 'X-CSRF-Token';

    public function handle(Request $request, callable $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Only check CSRF for state-changing methods
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $this->validateToken($request);
        }
        
        return $next($request);
    }
    
    /**
     * Validate CSRF token
     *
     * @param Request $request
     * @return void
     * @throws AuthorizationException
     */
    private function validateToken(Request $request): void
    {
        // Get token from header or request body
        $token = $request->getHeader($this->headerName) 
            ?? $request->input($this->tokenKey);
        
        if (!$token) {
            throw new AuthorizationException('CSRF token missing');
        }
        
        // Get expected token from session/storage
        $expectedToken = $this->getStoredToken();
        
        if (!$expectedToken) {
            throw new AuthorizationException('CSRF token not found in session');
        }
        
        if (!hash_equals($expectedToken, $token)) {
            throw new AuthorizationException('CSRF token mismatch');
        }
    }
    
    /**
     * Generate a new CSRF token
     *
     * @return string
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->storeToken($token);
        return $token;
    }
    
    /**
     * Get the current CSRF token
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        $token = $this->getStoredToken();
        
        if (!$token) {
            $token = $this->generateToken();
        }
        
        return $token;
    }
    
    /**
     * Store token in session
     *
     * @param string $token
     * @return void
     */
    private function storeToken(string $token): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['csrf_token'] = $token;
    }

    /**
     * Get stored token from session
     *
     * @return string|null
     */
    private function getStoredToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['csrf_token'] ?? null;
    }
}
