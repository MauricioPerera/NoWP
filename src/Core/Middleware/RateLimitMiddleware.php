<?php

/**
 * Rate Limit Middleware
 * 
 * Applies rate limiting to requests.
 * 
 * Requirements: 12.4
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Middleware;

use ChimeraNoWP\Core\MiddlewareInterface;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\RateLimiter;
use ChimeraNoWP\Core\Exceptions\RateLimitException;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiter $limiter;
    private int $maxAttempts;
    private int $decaySeconds;
    
    public function __construct(
        RateLimiter $limiter,
        int $maxAttempts = 60,
        int $decaySeconds = 60
    ) {
        $this->limiter = $limiter;
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }
    
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveRequestKey($request);
        
        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts, $this->decaySeconds)) {
            throw new RateLimitException(
                $this->limiter->availableIn($key, $this->decaySeconds),
                'Too many requests'
            );
        }
        
        $this->limiter->hit($key, $this->decaySeconds);
        
        $response = $next($request);
        
        // Add rate limit headers
        $attempts = $this->limiter->attempts($key);
        $response->setHeader('X-RateLimit-Limit', (string)$this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string)max(0, $this->maxAttempts - $attempts));
        
        return $response;
    }
    
    /**
     * Resolve unique key for the request
     *
     * @param Request $request
     * @return string
     */
    private function resolveRequestKey(Request $request): string
    {
        // Use REMOTE_ADDR as primary to prevent IP spoofing via headers
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Include path for per-endpoint rate limiting
        $path = $request->getPath();
        
        return md5($ip . ':' . $path);
    }
}
