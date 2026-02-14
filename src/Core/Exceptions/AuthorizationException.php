<?php

/**
 * Authorization Exception
 * 
 * Thrown when user lacks required permissions.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace Framework\Core\Exceptions;

class AuthorizationException extends HttpException
{
    public function __construct(
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            'INSUFFICIENT_PERMISSIONS',
            [],
            403,
            $previous
        );
    }
    
    protected function getDefaultErrorCode(): string
    {
        return 'INSUFFICIENT_PERMISSIONS';
    }
    
    protected function getDefaultMessage(): string
    {
        return 'You do not have permission to access this resource';
    }
}
