<?php

namespace Framework\Core\Middleware;

use Framework\Core\MiddlewareInterface;
use Framework\Core\Request;
use Framework\Core\Response;

/**
 * Logging Middleware
 * 
 * Example middleware that logs incoming requests.
 * Demonstrates the middleware pattern implementation.
 */
class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Log the incoming request
        $this->logRequest($request);
        
        // Call the next middleware in the pipeline
        $response = $next($request);
        
        // Log the response
        $this->logResponse($response);
        
        return $response;
    }

    /**
     * Log the incoming request
     * 
     * @param Request $request
     * @return void
     */
    private function logRequest(Request $request): void
    {
        error_log(sprintf(
            '[%s] %s %s',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getPath()
        ));
    }

    /**
     * Log the response
     * 
     * @param Response $response
     * @return void
     */
    private function logResponse(Response $response): void
    {
        error_log(sprintf(
            '[%s] Response: %d',
            date('Y-m-d H:i:s'),
            $response->getStatusCode()
        ));
    }
}
