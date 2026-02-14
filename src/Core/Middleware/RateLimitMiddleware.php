<?php

/**
 * Rate Limit Middleware
 * 
 * Applies rate limiting to requests.
 * 
 * Requirements: 12.4
 */

declare(strict_types=1);

namespace Framework\Core\Middleware;

use Framework\Core\MiddlewareInterface;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Core\RateLimiter;
use Framework\Core\Exceptions\RateLimitException;

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
        // Use IP address as key
        $ip = $request->getHeader('X-Forwarded-For') 
            ?? $request->getHeader('Remote-Addr') 
            ?? 'unknown';
        
        // Include path for per-endpoint rate limiting
        $path = $request->getPath();
        
        return md5($ip . ':' . $path);
    }
}
