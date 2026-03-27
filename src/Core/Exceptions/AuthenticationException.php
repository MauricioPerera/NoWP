<?php

/**
 * Authentication Exception
 * 
 * Thrown when authentication is required or fails.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Exceptions;

class AuthenticationException extends HttpException
{
    public function __construct(
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            'AUTHENTICATION_REQUIRED',
            [],
            401,
            $previous
        );
    }
    
    protected function getDefaultErrorCode(): string
    {
        return 'AUTHENTICATION_REQUIRED';
    }
    
    protected function getDefaultMessage(): string
    {
        return 'Valid authentication credentials are required';
    }
}
