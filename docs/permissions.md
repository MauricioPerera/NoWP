# Roles and Permissions System

The WordPress Alternative Framework includes a robust role-based access control (RBAC) system that allows you to manage user permissions effectively.

## Table of Contents

- [Overview](#overview)
- [User Roles](#user-roles)
- [Permissions](#permissions)
- [User Model](#user-model)
- [Permission Middleware](#permission-middleware)
- [Usage Examples](#usage-examples)

## Overview

The permission system is built around four core user roles, each with a specific set of permissions. The system supports:

- **Role-based permissions**: Each role has predefined permissions
- **Resource-based access control**: Users can only modify their own resources (for certain roles)
- **Middleware protection**: Routes can be protected with permission requirements
- **Flexible permission checks**: Multiple ways to check if a user can perform an action

## User Roles

The framework defines four user roles via the `UserRole` enum:

### Admin

Full system access with all permissions:
- Content management (create, read, update, delete, publish)
- User management (create, read, update, delete)
- Plugin management
- Settings management
- Media management (upload, delete)

### Editor

Content and media management without user administration:
- Content management (create, read, update, delete, publish)
- Media management (upload, delete)

### Author

Content creation with ownership restrictions:
- Content management (create, read, update, publish)
- Media upload
- **Note**: Authors can only update/delete their own content

### Subscriber

Read-only access:
- Content read permission only

## Permissions

Permissions follow a `resource.action` naming convention:

### Content Permissions
- `content.create` - Create new content
- `content.read` - Read/view content
- `content.update` - Update existing content
- `content.delete` - Delete content
- `content.publish` - Publish content

### User Permissions
- `user.create` - Create new users
- `user.read` - View user information
- `user.update` - Update user information
- `user.delete` - Delete users

### Media Permissions
- `media.upload` - Upload media files
- `media.delete` - Delete media files

### System Permissions
- `plugin.manage` - Activate/deactivate plugins
- `settings.manage` - Modify system settings

## User Model

The `User` class provides methods for permission checking:

### Properties

```php
public readonly int $id;
public string $email;
private string $passwordHash;
public string $displayName;
public UserRole $role;
public array $meta;
public readonly DateTime $createdAt;
public ?DateTime $lastLoginAt;
```

### Methods

#### `hasPermission(string $permission): bool`

Check if the user has a specific permission based on their role.

```php
$user = new User(1, 'admin@example.com', 'hash', 'Admin', UserRole::ADMIN);

if ($user->hasPermission('user.delete')) {
    // User can delete users
}
```

#### `can(string $action, ?object $resource = null): bool`

Check if the user can perform an action, optionally on a specific resource.

```php
// Check general permission
if ($user->can('content.create')) {
    // User can create content
}

// Check permission on specific resource
if ($user->can('content.update', $contentObject)) {
    // User can update this specific content
    // For authors, this checks ownership
}
```

**Resource Ownership**: When a resource is provided and the user is an Author, the system checks if the user owns the resource (via `getAuthorId()` method). Authors can only modify their own content.

#### `toArray(): array`

Convert user to array representation (excludes password hash).

```php
$userData = $user->toArray();
// Returns: ['id' => 1, 'email' => '...', 'display_name' => '...', 'role' => 'admin', ...]
```

#### `toJson(): string`

Convert user to JSON string.

```php
$json = $user->toJson();
```

## Permission Middleware

The `PermissionMiddleware` protects routes by requiring specific permissions.

### Constructor

```php
new PermissionMiddleware(string|array $permissions)
```

- **Single permission**: Pass a string
- **Multiple permissions**: Pass an array (all required)

### Usage in Routes

```php
use Framework\Auth\PermissionMiddleware;

// Single permission
$router->post('/api/content', $handler)
    ->middleware(new PermissionMiddleware('content.create'));

// Multiple permissions (all required)
$router->delete('/api/users/{id}', $handler)
    ->middleware(new PermissionMiddleware(['user.delete', 'user.read']));
```

### Response Codes

- **401 Unauthorized**: No authenticated user
- **403 Forbidden**: User lacks required permission

### Error Response Format

```json
{
    "error": {
        "code": "INSUFFICIENT_PERMISSIONS",
        "message": "You do not have permission to access this resource",
        "details": {
            "required_permission": "content.delete",
            "user_role": "author"
        }
    }
}
```

## Usage Examples

### Creating Users

```php
use Framework\Auth\User;
use Framework\Auth\UserRole;

$admin = new User(
    id: 1,
    email: 'admin@example.com',
    passwordHash: password_hash('password', PASSWORD_BCRYPT),
    displayName: 'Admin User',
    role: UserRole::ADMIN
);
```

### Checking Permissions

```php
// Simple permission check
if ($user->hasPermission('content.delete')) {
    // Delete content
}

// Action-based check
if ($user->can('content.publish')) {
    // Publish content
}
```

### Resource-Based Permissions

```php
class Content {
    public function __construct(private int $authorId) {}
    public function getAuthorId(): int { return $this->authorId; }
}

$content = new Content(authorId: 3);
$author = new User(3, 'author@example.com', 'hash', 'Author', UserRole::AUTHOR);

// Author can update their own content
if ($author->can('content.update', $content)) {
    // Update allowed
}

// But not others' content
$othersContent = new Content(authorId: 999);
if (!$author->can('content.update', $othersContent)) {
    // Update denied
}
```

### Protecting Routes

```php
use Framework\Core\Router;
use Framework\Auth\PermissionMiddleware;

$router = new Router();

// Only admins can access
$router->get('/api/admin/settings', function($request) {
    return Response::json(['settings' => '...']);
})->middleware(new PermissionMiddleware('settings.manage'));

// Editors and admins can access
$router->post('/api/content', function($request) {
    return Response::json(['message' => 'Content created']);
})->middleware(new PermissionMiddleware('content.create'));
```

### Custom Permission Logic

For more complex permission logic, you can extend the User model or create custom middleware:

```php
class CustomPermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $user = $request->getAttribute('user');
        
        // Custom logic
        if (!$this->customCheck($user, $request)) {
            return Response::error('Access denied', 'CUSTOM_PERMISSION_DENIED', 403);
        }
        
        return $next($request);
    }
    
    private function customCheck(User $user, Request $request): bool
    {
        // Your custom permission logic
        return true;
    }
}
```

## Best Practices

1. **Always use middleware for route protection**: Don't rely solely on controller-level checks
2. **Check ownership for resource modifications**: Use the `can()` method with the resource object
3. **Use specific permissions**: Prefer `content.update` over generic checks
4. **Combine with authentication**: Always use `AuthMiddleware` before `PermissionMiddleware`
5. **Log permission denials**: Track unauthorized access attempts for security monitoring

## Integration with Authentication

The permission system works seamlessly with the JWT authentication system:

```php
use Framework\Auth\AuthMiddleware;
use Framework\Auth\PermissionMiddleware;

// First authenticate, then check permissions
$router->post('/api/content', $handler)
    ->middleware(new AuthMiddleware($jwtManager))
    ->middleware(new PermissionMiddleware('content.create'));
```

The `AuthMiddleware` sets the authenticated user in the request, which the `PermissionMiddleware` then uses to check permissions.

## See Also

- [Authentication Documentation](./auth-middleware.md)
- [Middleware Documentation](./middleware.md)
- [Permission Example](../examples/permission-example.php)
