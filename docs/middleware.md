# Middleware System

The middleware system provides a powerful way to filter and modify HTTP requests and responses in your application. Middleware can be used for authentication, logging, CORS, rate limiting, and many other cross-cutting concerns.

## Table of Contents

- [Introduction](#introduction)
- [Creating Middleware](#creating-middleware)
- [Registering Middleware](#registering-middleware)
- [Middleware Pipeline](#middleware-pipeline)
- [Common Use Cases](#common-use-cases)
- [Best Practices](#best-practices)

## Introduction

Middleware acts as a bridge between the HTTP request and your application's route handler. Each middleware can:

1. Inspect and modify the incoming request
2. Pass control to the next middleware in the pipeline
3. Inspect and modify the outgoing response
4. Short-circuit the pipeline and return a response immediately

### How It Works

```
Request → Middleware 1 → Middleware 2 → Handler → Middleware 2 → Middleware 1 → Response
          ↓ before       ↓ before        ↓         ↑ after       ↑ after
```

## Creating Middleware

There are three ways to create middleware:

### 1. Using MiddlewareInterface

The recommended approach for reusable middleware:

```php
use Framework\Core\MiddlewareInterface;
use Framework\Core\Request;
use Framework\Core\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Check authentication
        $token = $request->getHeader('Authorization');
        
        if (!$this->isValidToken($token)) {
            return Response::error('Unauthorized', 'UNAUTHORIZED', 401);
        }
        
        // Continue to next middleware
        return $next($request);
    }
    
    private function isValidToken(?string $token): bool
    {
        // Validate token logic
        return $token === 'Bearer valid-token';
    }
}
```

### 2. Using Closures

Quick and simple for one-off middleware:

```php
$loggingMiddleware = function (Request $request, callable $next): Response {
    error_log("Request: {$request->getMethod()} {$request->getPath()}");
    
    $response = $next($request);
    
    error_log("Response: {$response->getStatusCode()}");
    
    return $response;
};
```

### 3. Using Class Names

Register middleware by class name (resolved from container):

```php
// Register in container
$container->bind(AuthMiddleware::class, function () {
    return new AuthMiddleware();
});

// Use by class name
$route->middleware(AuthMiddleware::class);
```

## Registering Middleware

### On Individual Routes

```php
$router->get('/users', [UserController::class, 'index'])
    ->middleware(new AuthMiddleware())
    ->middleware(new LoggingMiddleware());
```

### On Route Groups

Apply middleware to multiple routes at once:

```php
$router->group(['middleware' => new AuthMiddleware()], function (Router $router) {
    $router->get('/users', [UserController::class, 'index']);
    $router->get('/posts', [PostController::class, 'index']);
});
```

### Multiple Middleware in Groups

```php
$router->group([
    'prefix' => 'api',
    'middleware' => [
        new CorsMiddleware(),
        new AuthMiddleware(),
        new RateLimitMiddleware()
    ]
], function (Router $router) {
    // Routes here
});
```

### Nested Groups

Middleware from parent groups is inherited:

```php
$router->group(['middleware' => new AuthMiddleware()], function (Router $router) {
    // All routes here have AuthMiddleware
    
    $router->group(['middleware' => new AdminMiddleware()], function (Router $router) {
        // Routes here have both AuthMiddleware and AdminMiddleware
        $router->get('/admin/dashboard', [AdminController::class, 'dashboard']);
    });
});
```

## Middleware Pipeline

The `MiddlewarePipeline` class handles the execution of middleware in order:

```php
use Framework\Core\MiddlewarePipeline;

$pipeline = new MiddlewarePipeline($container);

$pipeline->pipe(new CorsMiddleware());
$pipeline->pipe(new AuthMiddleware());
$pipeline->pipe(new LoggingMiddleware());

$response = $pipeline->handle($request, function (Request $request): Response {
    // Final handler
    return Response::json(['message' => 'Success']);
});
```

### Execution Order

Middleware executes in a "Russian doll" pattern:

```php
// Registration order
$route->middleware($middleware1)
      ->middleware($middleware2)
      ->middleware($middleware3);

// Execution order
Middleware 1 (before)
  Middleware 2 (before)
    Middleware 3 (before)
      Handler
    Middleware 3 (after)
  Middleware 2 (after)
Middleware 1 (after)
```

### Short-Circuiting

Middleware can stop the pipeline by not calling `$next()`:

```php
class MaintenanceMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($this->isMaintenanceMode()) {
            // Don't call $next() - return immediately
            return Response::error(
                'Service temporarily unavailable',
                'MAINTENANCE',
                503
            );
        }
        
        return $next($request);
    }
}
```

## Common Use Cases

### Authentication

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->getHeader('Authorization');
        
        if (!$token) {
            return Response::error('Missing token', 'UNAUTHORIZED', 401);
        }
        
        // Validate token and attach user to request
        $user = $this->validateToken($token);
        
        if (!$user) {
            return Response::error('Invalid token', 'UNAUTHORIZED', 401);
        }
        
        // Store user in container for later use
        $this->container->instance(User::class, $user);
        
        return $next($request);
    }
}
```

### CORS

```php
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflightResponse();
        }
        
        $response = $next($request);
        
        // Add CORS headers
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        return $response;
    }
    
    private function preflightResponse(): Response
    {
        return (new Response('', 204))
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setHeader('Access-Control-Max-Age', '86400');
    }
}
```

### Rate Limiting

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    private CacheManager $cache;
    private int $maxAttempts = 60;
    private int $decayMinutes = 1;
    
    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $attempts = $this->cache->get($key, 0);
        
        if ($attempts >= $this->maxAttempts) {
            return Response::error(
                'Too many requests',
                'RATE_LIMIT_EXCEEDED',
                429
            )->setHeader('Retry-After', $this->decayMinutes * 60);
        }
        
        $this->cache->set($key, $attempts + 1, $this->decayMinutes * 60);
        
        $response = $next($request);
        
        // Add rate limit headers
        $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string) ($this->maxAttempts - $attempts - 1));
        
        return $response;
    }
    
    private function resolveRequestSignature(Request $request): string
    {
        return 'rate_limit:' . $request->getServer('REMOTE_ADDR');
    }
}
```

### Request Logging

```php
class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        
        // Log request
        error_log(sprintf(
            '[%s] %s %s',
            date('Y-m-d H:i:s'),
            $request->getMethod(),
            $request->getPath()
        ));
        
        $response = $next($request);
        
        // Log response with timing
        $duration = microtime(true) - $start;
        error_log(sprintf(
            '[%s] Response: %d (%.3fms)',
            date('Y-m-d H:i:s'),
            $response->getStatusCode(),
            $duration * 1000
        ));
        
        return $response;
    }
}
```

### JSON Response Wrapper

```php
class JsonResponseMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        
        // Ensure all responses are JSON
        if (!$response->getHeader('Content-Type')) {
            $response->setHeader('Content-Type', 'application/json');
        }
        
        return $response;
    }
}
```

## Best Practices

### 1. Keep Middleware Focused

Each middleware should have a single responsibility:

```php
// Good - focused on authentication
class AuthMiddleware implements MiddlewareInterface { }

// Bad - doing too much
class AuthAndLoggingAndCorsMiddleware implements MiddlewareInterface { }
```

### 2. Order Matters

Place middleware in logical order:

```php
$router->group([
    'middleware' => [
        new CorsMiddleware(),        // 1. Handle CORS first
        new LoggingMiddleware(),     // 2. Log all requests
        new RateLimitMiddleware(),   // 3. Rate limit before auth
        new AuthMiddleware(),        // 4. Authenticate
        new PermissionMiddleware()   // 5. Check permissions last
    ]
], function (Router $router) {
    // Routes
});
```

### 3. Use Dependency Injection

Inject dependencies through the constructor:

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JWTManager $jwt,
        private UserRepository $users
    ) {}
    
    public function handle(Request $request, callable $next): Response
    {
        // Use injected dependencies
        $token = $this->jwt->parseToken($request->getHeader('Authorization'));
        $user = $this->users->find($token['sub']);
        
        return $next($request);
    }
}
```

### 4. Make Middleware Configurable

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheManager $cache,
        private int $maxAttempts = 60,
        private int $decayMinutes = 1
    ) {}
}

// Usage
$route->middleware(new RateLimitMiddleware($cache, 100, 5));
```

### 5. Handle Errors Gracefully

```php
class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        try {
            return $next($request);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            
            return Response::error(
                'Internal server error',
                'SERVER_ERROR',
                500
            );
        }
    }
}
```

### 6. Test Middleware Independently

```php
it('blocks unauthenticated requests', function () {
    $middleware = new AuthMiddleware();
    $request = new Request('GET', '/protected', [], [], []);
    
    $response = $middleware->handle($request, function () {
        return Response::json(['data' => 'protected']);
    });
    
    expect($response->getStatusCode())->toBe(401);
});
```

## Conclusion

The middleware system provides a clean, composable way to handle cross-cutting concerns in your application. By following these patterns and best practices, you can build maintainable and testable middleware that enhances your application's functionality.
