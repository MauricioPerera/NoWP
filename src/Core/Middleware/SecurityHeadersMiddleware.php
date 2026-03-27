<?php

/**
 * Security Headers Middleware
 * 
 * Adds security headers to all responses.
 * 
 * Requirements: 12.3
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Middleware;

use ChimeraNoWP\Core\MiddlewareInterface;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $headers;
    
    public function __construct(array $customHeaders = [])
    {
        $this->headers = array_merge($this->getDefaultHeaders(), $customHeaders);
    }
    
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        
        // Add security headers to response
        foreach ($this->headers as $name => $value) {
            $response->setHeader($name, $value);
        }
        
        return $response;
    }
    
    /**
     * Get default security headers
     *
     * @return array
     */
    private function getDefaultHeaders(): array
    {
        return [
            // Prevent clickjacking
            'X-Frame-Options' => 'SAMEORIGIN',
            
            // Prevent MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',
            
            // Enable XSS protection
            'X-XSS-Protection' => '1; mode=block',
            
            // Referrer policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            
            // Content Security Policy (basic)
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;",
            
            // Permissions policy
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            
            // Strict Transport Security (HSTS) - enabled when serving over HTTPS
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];

        // Only include HSTS if connection is actually HTTPS
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            unset($headers['Strict-Transport-Security']);
        }
    }
}
