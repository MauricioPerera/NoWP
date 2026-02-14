<?php

/**
 * Exception Handler
 * 
 * Global exception handler that converts exceptions to HTTP responses
 * and logs errors appropriately.
 * 
 * Requirements: 12.1
 */

declare(strict_types=1);

namespace Framework\Core;

use Framework\Core\Exceptions\HttpException;
use Framework\Core\Exceptions\ServerException;
use Throwable;

class ExceptionHandler
{
    private bool $debug;
    private ?string $logPath;
    
    public function __construct(bool $debug = false, ?string $logPath = null)
    {
        $this->debug = $debug;
        $this->logPath = $logPath;
    }
    
    /**
     * Handle an exception and return appropriate response
     *
     * @param Throwable $e Exception to handle
     * @return Response
     */
    public function handle(Throwable $e): Response
    {
        // Log the exception
        $this->logException($e);
        
        // Convert to HTTP response
        if ($e instanceof HttpException) {
            return $e->toResponse();
        }
        
        // Handle unexpected exceptions
        if ($this->debug) {
            return $this->debugResponse($e);
        }
        
        // Return generic error in production
        return (new ServerException())->toResponse();
    }
    
    /**
     * Log exception details
     *
     * @param Throwable $e Exception to log
     * @return void
     */
    public function logException(Throwable $e): void
    {
        if (!$this->logPath) {
            return;
        }
        
        $level = $this->getLogLevel($e);
        $message = $this->formatLogMessage($e);
        
        $this->writeLog($level, $message);
    }
    
    /**
     * Create debug response with full error details
     *
     * @param Throwable $e Exception
     * @return Response
     */
    private function debugResponse(Throwable $e): Response
    {
        return Response::json([
            'error' => [
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->formatTrace($e->getTrace())
            ]
        ], 500);
    }
    
    /**
     * Get log level for exception
     *
     * @param Throwable $e Exception
     * @return string
     */
    private function getLogLevel(Throwable $e): string
    {
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            
            if ($statusCode >= 500) {
                return 'ERROR';
            }
            
            if ($statusCode >= 400) {
                return 'WARNING';
            }
            
            return 'INFO';
        }
        
        return 'ERROR';
    }
    
    /**
     * Format exception for logging
     *
     * @param Throwable $e Exception
     * @return string
     */
    private function formatLogMessage(Throwable $e): string
    {
        $message = sprintf(
            "[%s] %s: %s in %s:%d\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
        
        if ($e instanceof HttpException) {
            $message .= sprintf(
                "Error Code: %s, Status: %d\n",
                $e->getErrorCode(),
                $e->getStatusCode()
            );
            
            if (!empty($e->getDetails())) {
                $message .= "Details: " . json_encode($e->getDetails()) . "\n";
            }
        }
        
        $message .= "Stack trace:\n" . $e->getTraceAsString() . "\n";
        
        if ($e->getPrevious()) {
            $message .= "\nPrevious exception:\n" . $this->formatLogMessage($e->getPrevious());
        }
        
        return $message;
    }
    
    /**
     * Write log message to file
     *
     * @param string $level Log level
     * @param string $message Log message
     * @return void
     */
    private function writeLog(string $level, string $message): void
    {
        if (!$this->logPath) {
            return;
        }
        
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logMessage = sprintf("[%s] %s\n", $level, $message);
        file_put_contents($this->logPath, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Format stack trace for debug output
     *
     * @param array $trace Stack trace
     * @return array
     */
    private function formatTrace(array $trace): array
    {
        return array_map(function ($item) {
            return [
                'file' => $item['file'] ?? 'unknown',
                'line' => $item['line'] ?? 0,
                'function' => $item['function'] ?? 'unknown',
                'class' => $item['class'] ?? null,
            ];
        }, array_slice($trace, 0, 10)); // Limit to 10 frames
    }
    
    /**
     * Register as global exception handler
     *
     * @return void
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handle']);
    }
}
