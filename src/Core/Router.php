<?php

declare(strict_types=1);

namespace ChimeraNoWP\Core;

/**
 * Router Class
 * 
 * Handles route registration and matching for HTTP requests.
 */
class Router
{
    /**
     * Registered routes
     * @var array<Route>
     */
    private array $routes = [];

    /**
     * Current group attributes (prefix, middleware)
     * @var array<string, mixed>
     */
    private array $groupStack = [];

    /**
     * Register a GET route
     * 
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     * 
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     * 
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route
     * 
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a PATCH route
     * 
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Add a route to the router
     * 
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    private function addRoute(string $method, string $path, callable|array $handler): Route
    {
        // Apply group prefix if in a group
        $path = $this->applyGroupPrefix($path);
        
        $route = new Route($method, $path, $handler);
        
        // Apply group middleware if in a group
        $this->applyGroupMiddleware($route);
        
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Register a route group with shared attributes
     * 
     * @param array $attributes Group attributes (prefix, middleware)
     * @param callable $callback Callback to register routes within the group
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Push group attributes onto the stack
        $this->groupStack[] = $attributes;
        
        // Execute the callback to register routes
        call_user_func($callback, $this);
        
        // Pop the group attributes from the stack
        array_pop($this->groupStack);
    }

    /**
     * Apply group prefix to path
     * 
     * @param string $path
     * @return string
     */
    private function applyGroupPrefix(string $path): string
    {
        $prefix = '';
        
        // Collect all prefixes from the group stack
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        
        if ($prefix === '') {
            return $path;
        }
        
        // Combine prefix with path
        $path = '/' . trim($path, '/');
        return $prefix . $path;
    }

    /**
     * Apply group middleware to route
     * 
     * @param Route $route
     * @return void
     */
    private function applyGroupMiddleware(Route $route): void
    {
        // Collect all middleware from the group stack
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $middleware = $group['middleware'];
                
                // Handle both single middleware and array of middleware
                if (!is_array($middleware)) {
                    $middleware = [$middleware];
                }
                
                foreach ($middleware as $mw) {
                    $route->middleware($mw);
                }
            }
        }
    }

    /**
     * Match a request to a route
     * 
     * @param Request $request
     * @return Route|null
     */
    public function match(Request $request): ?Route
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Get all registered routes
     * 
     * @return array<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
