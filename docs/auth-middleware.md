# AuthMiddleware Documentation

## Overview

The `AuthMiddleware` is a middleware component that validates JWT tokens in incoming HTTP requests. It ensures that only authenticated users can access protected routes.

## Requirements

**Validates:** Requirements 3.3 - JWT token validation and user data injection

## Features

- ✅ Extracts JWT tokens from `Authorization` header
- ✅ Supports both `Bearer <token>` and `<token>` formats
- ✅ Validates token signature and structure
- ✅ Detects and rejects expired tokens
- ✅ Returns appropriate 401 error responses
- ✅ Injects authenticated user data into the request
- ✅ Integrates seamlessly with middleware pipeline

## Installation

The AuthMiddleware is located at `src/Auth/AuthMiddleware.php` and requires:
- `Framework\Auth\JWTManager` - for token validation
- `Framework\Core\MiddlewareInterface` - middleware contract

## Usage

### Basic Setup

```php
use Framework\Auth\AuthMiddleware;
use Framework\Auth\JWTManager;
use Framework\Core\Router;

// Initialize JWT Manager
$jwtManager = new JWTManager('your-secret-key', 3600);

// Create middleware instance
$authMiddleware = new AuthMiddleware($jwtManager);

// Apply to routes
$router->group(['middleware' => [$authMiddleware]], function ($router) {
    $router->get('/api/protected', function ($request) {
        // This route requires authentication
        $user = $request->user();
        return Response::success(['user' => $user]);
    });
});
```

### Accessing User Data

Once authenticated, user data is available in the request:

```php
$router->get('/api/profile', function (Request $request) {
    // Get user data
    $user = $request->user();
    
    // Access user properties
    $userId = $user['id'];      // User ID from JWT 'sub' claim
    $email = $user['email'];    // User email
    $role = $user['role'];      // User role
    
    return Response::success(['profile' => $user]);
});
```

### Alternative Access Methods

```php
// Using getAttribute
$user = $request->getAttribute('user');

// Check if user is authenticated
if ($request->hasAttribute('user')) {
    // User is authenticated
}
```

## Error Responses

### Missing Token (401)

```json
{
    "error": {
        "code": "AUTHENTICATION_REQUIRED",
        "message": "Authentication required"
    }
}
```

### Invalid Token (401)

```json
{
    "error": {
        "code": "INVALID_TOKEN",
        "message": "Invalid authentication token"
    }
}
```

### Expired Token (401)

```json
{
    "error": {
        "code": "TOKEN_EXPIRED",
        "message": "Token has expired"
    }
}
```

## Request Headers

### With Bearer Prefix (Recommended)

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Without Bearer Prefix

```
Authorization: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## Implementation Details

### Token Extraction

The middleware extracts tokens from the `Authorization` header and automatically handles the `Bearer` prefix:

```php
private function extractToken(Request $request): ?string
{
    $authHeader = $request->getHeader('Authorization');
    
    if ($authHeader === null) {
        return null;
    }
    
    // Remove "Bearer " prefix if present
    if (str_starts_with($authHeader, 'Bearer ')) {
        return substr($authHeader, 7);
    }
    
    return $authHeader;
}
```

### Token Validation Flow

1. Extract token from Authorization header
2. Return 401 if no token provided
3. Parse and validate token using JWTManager
4. Check for expiration in exception message
5. Inject user data into request if valid
6. Continue to next middleware/handler

### User Data Injection

User data from the JWT payload is stored in request attributes:

```php
private function injectUserData(Request $request, array $payload): void
{
    $request->setAttribute('user', [
        'id' => $payload['sub'] ?? null,
        'email' => $payload['email'] ?? null,
        'role' => $payload['role'] ?? null,
    ]);
}
```

## Testing

### Unit Tests

Located at `tests/Unit/Auth/AuthMiddlewareTest.php`:

- ✅ Returns 401 when no authorization header is present
- ✅ Returns 401 when token is invalid
- ✅ Returns 401 when token is expired
- ✅ Allows request with valid token and injects user data
- ✅ Extracts token without Bearer prefix
- ✅ Provides user data via convenience method
- ✅ Handles malformed authorization header gracefully
- ✅ Does not call next middleware when authentication fails
- ✅ Calls next middleware when authentication succeeds

### Integration Tests

Located at `tests/Integration/AuthMiddlewareIntegrationTest.php`:

- ✅ Protects routes with authentication middleware
- ✅ Allows authenticated requests through pipeline
- ✅ Chains multiple middleware correctly
- ✅ Stops pipeline when auth fails

## Examples

See `examples/auth-middleware-example.php` for a complete working example with:
- Public routes
- Login endpoint
- Protected routes
- Role-based access control
- cURL usage examples

## Security Considerations

1. **Secret Key**: Always use a strong, random secret key for JWT signing
2. **HTTPS**: Always use HTTPS in production to prevent token interception
3. **Token Storage**: Store tokens securely on the client (avoid localStorage for sensitive apps)
4. **Token Expiration**: Use reasonable TTL values (e.g., 1 hour for access tokens)
5. **Refresh Tokens**: Implement refresh token mechanism for long-lived sessions

## Related Components

- `JWTManager` - Token generation and validation
- `PasswordHasher` - Password hashing for authentication
- `MiddlewarePipeline` - Middleware execution pipeline
- `Request` - HTTP request with attribute storage
- `Response` - HTTP response helpers

## Future Enhancements

Potential improvements for future versions:

- Token refresh mechanism
- Token revocation/blacklist
- Multiple authentication strategies (API keys, OAuth)
- Rate limiting per user
- Audit logging of authentication events
