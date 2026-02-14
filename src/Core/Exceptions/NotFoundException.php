<?php

/**
 * Not Found Exception
 * 
 * Thrown when a requested resource is not found.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace Framework\Core\Exceptions;

class NotFoundException extends HttpException
{
    public function __construct(
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            'RESOURCE_NOT_FOUND',
            [],
            404,
            $previous
        );
    }
    
    protected function getDefaultErrorCode(): string
    {
        return 'RESOURCE_NOT_FOUND';
    }
    
    protected function getDefaultMessage(): string
    {
        return 'The requested resource was not found';
    }
}
