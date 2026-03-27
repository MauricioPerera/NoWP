<?php

namespace ChimeraNoWP\Core;

/**
 * Middleware Interface
 * 
 * Defines the contract for middleware components that can process
 * HTTP requests before they reach the route handler.
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     * 
     * @param Request $request The incoming request
     * @param callable $next The next middleware in the pipeline
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
