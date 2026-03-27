<?php

/**
 * AuthMiddleware Usage Example
 * 
 * This example demonstrates how to use AuthMiddleware to protect routes
 * with JWT authentication.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ChimeraNoWP\Auth\AuthMiddleware;
use ChimeraNoWP\Auth\JWTManager;
use ChimeraNoWP\Core\Application;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;
use ChimeraNoWP\Core\Router;

// Initialize container and services
$container = new Container();

// Configure JWT Manager
$jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production';
$jwtTTL = 3600; // 1 hour
$jwtManager = new JWTManager($jwtSecret, $jwtTTL);

$container->singleton(JWTManager::class, fn() => $jwtManager);
$container->singleton(AuthMiddleware::class, fn($c) => new AuthMiddleware($c->resolve(JWTManager::class)));

// Create router
$router = new Router($container);

// Public route - no authentication required
$router->get('/api/public', function (Request $request): Response {
    return Response::success([
        'message' => 'This is a public endpoint',
        'timestamp' => time()
    ]);
});

// Login route - generates JWT token
$router->post('/api/login', function (Request $request) use ($jwtManager): Response {
    $email = $request->input('email');
    $password = $request->input('password');
    
    // In a real application, validate credentials against database
    // This is just an example
    if ($email === 'admin@example.com' && $password === 'password') {
        $token = $jwtManager->generateToken(
            userId: 1,
            email: $email,
            role: 'admin'
        );
        
        return Response::success([
            'token' => $token,
            'expires_in' => 3600
        ]);
    }
    
    return Response::error('Invalid credentials', 'INVALID_CREDENTIALS', 401);
});

// Protected routes - require authentication
$router->group(['middleware' => [AuthMiddleware::class]], function (Router $router) {
    
    // Get current user info
    $router->get('/api/me', function (Request $request): Response {
        $user = $request->user();
        
        return Response::success([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    });
    
    // Protected resource
    $router->get('/api/users', function (Request $request): Response {
        $user = $request->user();
        
        // Check if user has admin role
        if ($user['role'] !== 'admin') {
            return Response::error(
                'You do not have permission to access this resource',
                'INSUFFICIENT_PERMISSIONS',
                403
            );
        }
        
        return Response::success([
            'users' => [
                ['id' => 1, 'email' => 'admin@example.com', 'role' => 'admin'],
                ['id' => 2, 'email' => 'user@example.com', 'role' => 'editor'],
            ]
        ]);
    });
    
    // Create content (protected)
    $router->post('/api/content', function (Request $request): Response {
        $user = $request->user();
        
        $title = $request->input('title');
        $content = $request->input('content');
        
        // In a real application, save to database
        return Response::success([
            'id' => 123,
            'title' => $title,
            'content' => $content,
            'author_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ], 'Content created successfully', 201);
    });
});

// Example usage with cURL:
/*

# 1. Try to access protected route without token (should fail with 401)
curl -X GET http://localhost:8000/api/me

# 2. Login to get a token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# Response: {"success":true,"message":"Success","data":{"token":"eyJ0eXAiOiJKV1QiLCJhbGc...","expires_in":3600}}

# 3. Use the token to access protected route
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."

# Response: {"success":true,"message":"Success","data":{"user":{"id":1,"email":"admin@example.com","role":"admin"}}}

# 4. Create content with authentication
curl -X POST http://localhost:8000/api/content \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{"title":"My Post","content":"This is my content"}'

# 5. Try with expired token (should fail with 401)
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer <expired-token>"

# Response: {"error":{"code":"TOKEN_EXPIRED","message":"Token has expired"}}

*/

echo "AuthMiddleware example configured. See comments for cURL usage examples.\n";
