<?php

/**
 * Rate Limit Exception
 * 
 * Thrown when rate limit is exceeded.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Exceptions;

use ChimeraNoWP\Core\Response;

class RateLimitException extends HttpException
{
    private int $retryAfter;
    
    public function __construct(
        int $retryAfter = 60,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        $this->retryAfter = $retryAfter;
        
        parent::__construct(
            $message,
            'RATE_LIMIT_EXCEEDED',
            ['retry_after' => $retryAfter],
            429,
            $previous
        );
    }
    
    public function toResponse(): Response
    {
        $response = parent::toResponse();
        $response->setHeader('Retry-After', (string)$this->retryAfter);
        
        return $response;
    }
    
    protected function getDefaultErrorCode(): string
    {
        return 'RATE_LIMIT_EXCEEDED';
    }
    
    protected function getDefaultMessage(): string
    {
        return 'Too many requests, please try again later';
    }
    
    /**
     * Get retry after seconds
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
