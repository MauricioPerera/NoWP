<?php

/**
 * Permission System Example
 * 
 * This example demonstrates how to use the roles and permissions system
 * in the WordPress Alternative Framework.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ChimeraNoWP\Auth\User;
use ChimeraNoWP\Auth\UserRole;
use ChimeraNoWP\Auth\PermissionMiddleware;
use ChimeraNoWP\Core\Router;
use ChimeraNoWP\Core\Request;
use ChimeraNoWP\Core\Response;

// ============================================================================
// 1. Creating Users with Different Roles
// ============================================================================

$admin = new User(
    id: 1,
    email: 'admin@example.com',
    passwordHash: password_hash('admin123', PASSWORD_BCRYPT),
    displayName: 'Admin User',
    role: UserRole::ADMIN
);

$editor = new User(
    id: 2,
    email: 'editor@example.com',
    passwordHash: password_hash('editor123', PASSWORD_BCRYPT),
    displayName: 'Editor User',
    role: UserRole::EDITOR
);

$author = new User(
    id: 3,
    email: 'author@example.com',
    passwordHash: password_hash('author123', PASSWORD_BCRYPT),
    displayName: 'Author User',
    role: UserRole::AUTHOR
);

$subscriber = new User(
    id: 4,
    email: 'subscriber@example.com',
    passwordHash: password_hash('subscriber123', PASSWORD_BCRYPT),
    displayName: 'Subscriber User',
    role: UserRole::SUBSCRIBER
);

// ============================================================================
// 2. Checking Permissions
// ============================================================================

echo "=== Permission Checks ===\n\n";

// Check if users have specific permissions
echo "Admin can delete users: " . ($admin->hasPermission('user.delete') ? 'Yes' : 'No') . "\n";
echo "Editor can delete users: " . ($editor->hasPermission('user.delete') ? 'Yes' : 'No') . "\n";
echo "Author can create content: " . ($author->hasPermission('content.create') ? 'Yes' : 'No') . "\n";
echo "Subscriber can create content: " . ($subscriber->hasPermission('content.create') ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// 3. Using the can() Method
// ============================================================================

echo "=== Using can() Method ===\n\n";

// Check general permissions
echo "Admin can manage settings: " . ($admin->can('settings.manage') ? 'Yes' : 'No') . "\n";
echo "Editor can publish content: " . ($editor->can('content.publish') ? 'Yes' : 'No') . "\n";
echo "Author can delete content: " . ($author->can('content.delete') ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// 4. Resource-Based Permissions
// ============================================================================

echo "=== Resource-Based Permissions ===\n\n";

// Mock content resource
class Content {
    public function __construct(private int $authorId) {}
    public function getAuthorId(): int { return $this->authorId; }
}

$authorContent = new Content(3); // Owned by author (id: 3)
$otherContent = new Content(999); // Owned by someone else

echo "Author can update their own content: " . 
    ($author->can('content.update', $authorContent) ? 'Yes' : 'No') . "\n";
echo "Author can update other's content: " . 
    ($author->can('content.update', $otherContent) ? 'Yes' : 'No') . "\n";
echo "Admin can update other's content: " . 
    ($admin->can('content.update', $otherContent) ? 'Yes' : 'No') . "\n\n";

// ============================================================================
// 5. Using PermissionMiddleware in Routes
// ============================================================================

echo "=== Using PermissionMiddleware ===\n\n";

$router = new Router();

// Route that requires content.create permission
$router->post('/api/content', function (Request $request): Response {
    return Response::json(['message' => 'Content created successfully']);
})->middleware(new PermissionMiddleware('content.create'));

// Route that requires multiple permissions
$router->delete('/api/users/{id}', function (Request $request): Response {
    return Response::json(['message' => 'User deleted successfully']);
})->middleware(new PermissionMiddleware(['user.delete', 'user.read']));

// Route that requires admin-only permission
$router->post('/api/plugins/activate', function (Request $request): Response {
    return Response::json(['message' => 'Plugin activated successfully']);
})->middleware(new PermissionMiddleware('plugin.manage'));

echo "Routes configured with permission middleware\n";
echo "- POST /api/content requires 'content.create'\n";
echo "- DELETE /api/users/{id} requires 'user.delete' and 'user.read'\n";
echo "- POST /api/plugins/activate requires 'plugin.manage'\n\n";

// ============================================================================
// 6. Role Permissions Overview
// ============================================================================

echo "=== Role Permissions Overview ===\n\n";

$roles = [
    'Admin' => UserRole::ADMIN,
    'Editor' => UserRole::EDITOR,
    'Author' => UserRole::AUTHOR,
    'Subscriber' => UserRole::SUBSCRIBER,
];

foreach ($roles as $name => $role) {
    echo "{$name} permissions:\n";
    $permissions = $role->getPermissions();
    foreach ($permissions as $permission) {
        echo "  - {$permission}\n";
    }
    echo "\n";
}

// ============================================================================
// 7. Converting User to Array/JSON
// ============================================================================

echo "=== User Serialization ===\n\n";

echo "User as array:\n";
print_r($author->toArray());

echo "\nUser as JSON:\n";
echo $author->toJson() . "\n\n";

echo "Note: Password hash is never included in serialized output for security.\n";
