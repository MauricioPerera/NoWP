<?php

namespace Framework\Core;

/**
 * Route Class
 * 
 * Represents a single route with its method, path, handler, and middleware.
 */
class Route
{
    /**
     * HTTP method
     */
    private string $method;

    /**
     * Route path pattern
     */
    private string $path;

    /**
     * Route handler (callable or [Controller, method])
     */
    private mixed $handler;

    /**
     * Route parameters extracted from path
     * @var array<string, string>
     */
    private array $parameters = [];

    /**
     * Middleware for this route
     * @var array<callable|string|MiddlewareInterface>
     */
    private array $middleware = [];

    /**
     * Create a new Route instance
     * 
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     */
    public function __construct(string $method, string $path, callable|array $handler)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
    }

    /**
     * Check if this route matches the given method and path
     * 
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function matches(string $method, string $path): bool
    {
        if ($this->method !== strtoupper($method)) {
            return false;
        }

        // Convert route pattern to regex
        $pattern = $this->convertToRegex($this->path);

        if (preg_match($pattern, $path, $matches)) {
            // Extract named parameters
            $this->parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return true;
        }

        return false;
    }

    /**
     * Convert route path to regex pattern
     * 
     * @param string $path
     * @return string
     */
    private function convertToRegex(string $path): string
    {
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $path);
        
        // Convert {param} to named capture groups
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    /**
     * Execute the route handler
     * 
     * @param Container $container
     * @return Response
     */
    public function execute(Container $container): Response
    {
        $request = $container->resolve(Request::class);
        
        // Create middleware pipeline
        $pipeline = new MiddlewarePipeline($container);
        
        // Add all middleware to the pipeline
        foreach ($this->middleware as $middleware) {
            $pipeline->pipe($middleware);
        }
        
        // Execute the pipeline with the route handler as the destination
        return $pipeline->handle($request, function (Request $request) use ($container): Response {
            return $this->executeHandler($container);
        });
    }

    /**
     * Execute the route handler
     * 
     * @param Container $container
     * @return Response
     */
    private function executeHandler(Container $container): Response
    {
        // Execute the handler
        if (is_callable($this->handler)) {
            $result = call_user_func($this->handler, ...$this->parameters);
        } elseif (is_array($this->handler)) {
            [$controller, $method] = $this->handler;
            
            // Resolve controller from container
            if (is_string($controller)) {
                $controller = $container->resolve($controller);
            }
            
            $result = call_user_func([$controller, $method], ...$this->parameters);
        } else {
            throw new \RuntimeException('Invalid route handler');
        }

        // Convert result to Response if needed
        if (!$result instanceof Response) {
            if (is_array($result) || is_object($result)) {
                return Response::json($result);
            }
            return new Response((string) $result);
        }

        return $result;
    }

    /**
     * Add middleware to this route
     * 
     * @param callable|string|MiddlewareInterface $middleware
     * @return self
     */
    public function middleware(callable|string|MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Get all middleware for this route
     * 
     * @return array<callable|string|MiddlewareInterface>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get route method
     * 
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get route path
     * 
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get route parameters
     * 
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a specific parameter
     * 
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function getParameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }
}
