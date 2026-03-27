<?php

/**
 * Base HTTP Exception
 * 
 * Base class for all HTTP exceptions with response conversion.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace ChimeraNoWP\Core\Exceptions;

use ChimeraNoWP\Core\Response;
use Exception;

abstract class HttpException extends Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected array $details = [];
    
    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $details = [],
        int $statusCode = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode ?: $this->getDefaultErrorCode();
        $this->details = $details;
    }
    
    /**
     * Convert exception to HTTP response
     *
     * @return Response
     */
    public function toResponse(): Response
    {
        $error = [
            'code' => $this->errorCode,
            'message' => $this->message ?: $this->getDefaultMessage(),
            'timestamp' => date('c'),
        ];
        
        if (!empty($this->details)) {
            $error['details'] = $this->details;
        }
        
        return Response::json(['error' => $error], $this->statusCode);
    }
    
    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    /**
     * Get error code
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
    
    /**
     * Get error details
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }
    
    /**
     * Get default error code for this exception type
     *
     * @return string
     */
    abstract protected function getDefaultErrorCode(): string;
    
    /**
     * Get default error message for this exception type
     *
     * @return string
     */
    abstract protected function getDefaultMessage(): string;
}
