<?php

/**
 * Validation Exception
 * 
 * Thrown when request data fails validation.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Exceptions;

class ValidationException extends HttpException
{
    public function __construct(
        string $message = '',
        array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            'VALIDATION_ERROR',
            ['validation_errors' => $errors],
            400,
            $previous
        );
    }
    
    protected function getDefaultErrorCode(): string
    {
        return 'VALIDATION_ERROR';
    }
    
    protected function getDefaultMessage(): string
    {
        return 'The request data is invalid';
    }
    
    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->details['validation_errors'] ?? [];
    }
}
