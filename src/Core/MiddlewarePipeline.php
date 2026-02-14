<?php

namespace Framework\Core;

/**
 * Middleware Pipeline
 * 
 * Executes a stack of middleware in order, passing the request through
 * each middleware until reaching the final handler.
 */
class MiddlewarePipeline
{
    /**
     * The container instance for resolving middleware
     */
    private Container $container;

    /**
     * Stack of middleware to execute
     * @var array<callable|string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Create a new middleware pipeline
     * 
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Add middleware to the pipeline
     * 
     * @param callable|string|MiddlewareInterface $middleware
     * @return self
     */
    public function pipe(callable|string|MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Execute the middleware pipeline
     * 
     * @param Request $request
     * @param callable $destination Final handler to execute after all middleware
     * @return Response
     */
    public function handle(Request $request, callable $destination): Response
    {
        // Build the middleware chain from the end backwards
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (callable $next, mixed $middleware) {
                return function (Request $request) use ($middleware, $next): Response {
                    return $this->executeMiddleware($middleware, $request, $next);
                };
            },
            $destination
        );

        // Execute the pipeline
        return $pipeline($request);
    }

    /**
     * Execute a single middleware
     * 
     * @param mixed $middleware
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    private function executeMiddleware(
        mixed $middleware,
        Request $request,
        callable $next
    ): Response {
        // If middleware is a string, resolve it from the container
        if (is_string($middleware)) {
            $middleware = $this->container->resolve($middleware);
        }

        // If middleware implements MiddlewareInterface, call handle method
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->handle($request, $next);
        }

        // If middleware is callable, call it directly
        if (is_callable($middleware)) {
            return $middleware($request, $next);
        }

        throw new \InvalidArgumentException(
            'Middleware must be callable, a class name, or implement MiddlewareInterface'
        );
    }

    /**
     * Get all middleware in the pipeline
     * 
     * @return array<callable|string|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
