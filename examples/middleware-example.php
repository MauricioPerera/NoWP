<?php

/**
 * Middleware System Example
 * 
 * This example demonstrates how to use the middleware system in the framework.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ChimeraNoWP\Core\Application;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\MiddlewareInterface;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Router;

// Create application
$config = [
    'app' => [
        'name' => 'Middleware Example',
        'debug' => true
    ]
];

$app = new Application($config);
$app->boot();

// Get router from container
$router = $app->getContainer()->resolve(Router::class);

// Example 1: Simple callable middleware
$router->get('/example1', function () {
    return Response::json(['message' => 'Example 1']);
})->middleware(function (Request $request, callable $next): Response {
    echo "Before handler\n";
    $response = $next($request);
    echo "After handler\n";
    return $response;
});

// Example 2: Multiple middleware in order
$router->get('/example2', function () {
    return Response::json(['message' => 'Example 2']);
})
->middleware(function (Request $request, callable $next): Response {
    echo "Middleware 1 - Before\n";
    $response = $next($request);
    echo "Middleware 1 - After\n";
    return $response;
})
->middleware(function (Request $request, callable $next): Response {
    echo "Middleware 2 - Before\n";
    $response = $next($request);
    echo "Middleware 2 - After\n";
    return $response;
});

// Example 3: Middleware that modifies the response
$router->get('/example3', function () {
    return Response::json(['data' => 'original']);
})->middleware(function (Request $request, callable $next): Response {
    $response = $next($request);
    
    // Add a custom header
    $response->setHeader('X-Custom-Header', 'Modified by middleware');
    
    return $response;
});

// Example 4: Authentication middleware (short-circuit example)
$authMiddleware = function (Request $request, callable $next): Response {
    $token = $request->getHeader('Authorization');
    
    if (!$token || $token !== 'Bearer valid-token') {
        return Response::error('Unauthorized', 'UNAUTHORIZED', 401);
    }
    
    return $next($request);
};

$router->get('/protected', function () {
    return Response::json(['message' => 'Protected resource']);
})->middleware($authMiddleware);

// Example 5: Using MiddlewareInterface
class TimingMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        $response->setHeader('X-Response-Time', sprintf('%.3fms', $duration * 1000));
        
        return $response;
    }
}

$router->get('/timed', function () {
    usleep(10000); // Simulate some work
    return Response::json(['message' => 'Timed endpoint']);
})->middleware(new TimingMiddleware());

// Example 6: Route groups with shared middleware
$router->group(['prefix' => 'api', 'middleware' => $authMiddleware], function (Router $router) {
    $router->get('/users', function () {
        return Response::json(['users' => ['Alice', 'Bob']]);
    });
    
    $router->get('/posts', function () {
        return Response::json(['posts' => ['Post 1', 'Post 2']]);
    });
});

// Example 7: Nested groups with multiple middleware
$loggingMiddleware = function (Request $request, callable $next): Response {
    error_log(sprintf('[%s] %s %s', date('Y-m-d H:i:s'), $request->getMethod(), $request->getPath()));
    return $next($request);
};

$router->group(['prefix' => 'admin', 'middleware' => [$authMiddleware, $loggingMiddleware]], function (Router $router) {
    $router->get('/dashboard', function () {
        return Response::json(['message' => 'Admin dashboard']);
    });
    
    // Nested group with additional middleware
    $router->group(['prefix' => 'settings'], function (Router $router) {
        $router->get('/general', function () {
            return Response::json(['message' => 'General settings']);
        });
    });
});

// Example 8: CORS middleware
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        
        return $response;
    }
}

$router->group(['middleware' => new CorsMiddleware()], function (Router $router) {
    $router->get('/public-api/data', function () {
        return Response::json(['data' => 'Public data']);
    });
});

echo "Middleware examples registered successfully!\n";
echo "\nAvailable routes:\n";
echo "- GET /example1 - Simple middleware\n";
echo "- GET /example2 - Multiple middleware\n";
echo "- GET /example3 - Response modification\n";
echo "- GET /protected - Authentication (requires 'Authorization: Bearer valid-token')\n";
echo "- GET /timed - Timing middleware\n";
echo "- GET /api/users - Grouped with auth\n";
echo "- GET /api/posts - Grouped with auth\n";
echo "- GET /admin/dashboard - Multiple middleware\n";
echo "- GET /admin/settings/general - Nested groups\n";
echo "- GET /public-api/data - CORS enabled\n";
