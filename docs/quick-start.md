# Quick Start Guide

Welcome to the WordPress Alternative Framework! This guide will help you get started quickly.

## Installation

### Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- Composer

### Steps

1. **Clone or download the framework**

```bash
git clone https://github.com/your-repo/framework.git
cd framework
```

2. **Install dependencies**

```bash
composer install
```

3. **Configure environment**

Copy the example environment file:

```bash
copy .env.example .env
```

Edit `.env` and configure your database:

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

4. **Run the installer**

Navigate to `http://your-domain.com/install` in your browser and follow the installation wizard.

## Your First API Request

### Authentication

First, obtain an authentication token:

```bash
curl -X POST http://your-domain.com/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@example.com",
    "password": "your-password"
  }'
```

Response:

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

### Create Content

Use the token to create content:

```bash
curl -X POST http://your-domain.com/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "My First Post",
    "content": "Hello, World!",
    "type": "post",
    "status": "published"
  }'
```

### List Content

Retrieve all content:

```bash
curl -X GET http://your-domain.com/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

## Common Use Cases

### 1. Blog API

Create a simple blog API:

```php
// Register routes in public/index.php
$router->group(['prefix' => 'api', 'middleware' => AuthMiddleware::class], function($router) {
    // Posts
    $router->get('/posts', [ContentController::class, 'index']);
    $router->get('/posts/{id}', [ContentController::class, 'show']);
    $router->post('/posts', [ContentController::class, 'store']);
    $router->put('/posts/{id}', [ContentController::class, 'update']);
    $router->delete('/posts/{id}', [ContentController::class, 'destroy']);
});
```

### 2. File Upload

Upload files to your API:

```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);

const response = await fetch('http://your-domain.com/api/media', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
  },
  body: formData,
});

const data = await response.json();
console.log('File URL:', data.url);
```

### 3. Custom Plugin

Create a custom plugin:

```php
// plugins/my-plugin/my-plugin.php
<?php

use Framework\Plugin\PluginInterface;

class MyPlugin implements PluginInterface
{
    public function register(): void
    {
        // Register hooks
        $hooks = app()->resolve(HookSystem::class);
        
        $hooks->addFilter('content.render', function($content) {
            return strtoupper($content);
        });
    }
    
    public function boot(): void
    {
        // Plugin initialization
    }
    
    public function deactivate(): void
    {
        // Cleanup
    }
}

return new MyPlugin();
```

## Next Steps

- Read the [API Documentation](http://your-domain.com/api/docs)
- Explore [Authentication Guide](./auth-middleware.md)
- Learn about [Permissions](./permissions.md)
- Check out [Middleware](./middleware.md)

## Getting Help

- GitHub Issues: https://github.com/your-repo/framework/issues
- Documentation: https://docs.your-domain.com
- Community Forum: https://forum.your-domain.com
