<?php

/**
 * Server Exception
 * 
 * Thrown for internal server errors.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Exceptions;

class ServerException extends HttpException
{
    public function __construct(
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            'INTERNAL_SERVER_ERROR',
            [],
            500,
            $previous
        );
    }
    
    protected function getDefaultErrorCode(): string
    {
        return 'INTERNAL_SERVER_ERROR';
    }
    
    protected function getDefaultMessage(): string
    {
        return 'An unexpected error occurred';
    }
}
