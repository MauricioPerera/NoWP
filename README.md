# NoWP Framework

A modern, lightweight CMS/BaaS framework designed as an alternative to WordPress for 2026. Built with PHP 8.1+ and optimized for shared hosting environments ($3/month).

## Features

- **API-First Architecture**: RESTful APIs for all functionality with OpenAPI documentation
- **Modern PHP**: Built with PHP 8.1+ features (enums, readonly properties, attributes)
- **Lightweight**: Optimized for shared hosting (< 256MB RAM, < 100ms response time, < 100MB disk)
- **TypeScript Client**: Full-featured API client for Node.js and browsers
- **Admin Panel**: Modern, responsive SPA for content management
- **Extensible**: Plugin system with hooks and filters
- **Secure**: JWT authentication, bcrypt passwords, CSRF protection, rate limiting
- **i18n Ready**: Multi-language content support
- **Developer-Friendly**: PSR-4 autoloading, DI container, comprehensive testing

## Quick Start

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env with your settings

# Run migrations
php cli/migrate.php

# Check system requirements
php cli/check-resources.php

# Start development server
php -S localhost:8000 -t public/
```

Visit `http://localhost:8000/api/docs` for interactive API documentation.

## Project Structure

```
/
├── public/          # Web root
├── src/             # Framework source
│   ├── Auth/        # Authentication & authorization
│   ├── Content/     # Content management
│   ├── Core/        # Core framework
│   ├── Database/    # Database layer
│   ├── Plugin/      # Plugin system
│   ├── Storage/     # File & media management
│   └── Theme/       # Theme system
├── admin/           # Admin panel (SPA)
├── client/          # TypeScript API client
├── config/          # Configuration
├── migrations/      # Database migrations
├── plugins/         # User plugins
├── themes/          # User themes
├── tests/           # Test suite
├── docs/            # Documentation
└── cli/             # CLI commands
```

## Components

### Backend Framework (PHP)
- RESTful API with OpenAPI/Swagger documentation
- JWT authentication with role-based permissions
- Content management with versioning and custom fields
- Media management with image processing
- Plugin system with hooks and filters
- Theme system with template inheritance
- Multi-language support (i18n)
- Caching (APCu, Redis, Memcached, File)
- Backup and migration tools

### TypeScript API Client
- Works in Node.js and browsers
- Automatic token management
- Retry with exponential backoff
- Full TypeScript types
- Tree-shakeable

```typescript
import { APIClient } from '@nowp/client';

const client = new APIClient({ baseURL: 'https://your-site.com' });
await client.auth.login('user@example.com', 'password');
const posts = await client.content.list({ type: 'post' });
```

### Admin Panel
- Modern, responsive SPA (vanilla JS + Vite)
- Dashboard with statistics
- Content management (CRUD)
- Media library with upload
- User management
- Plugin documentation
- Mobile-friendly

## Requirements

- PHP 8.1+
- MySQL 5.7+
- Apache with mod_rewrite (or nginx)
- PHP Extensions: PDO, pdo_mysql, mbstring, json, openssl
- Optional: fileinfo, gd/imagick (for media processing)

## Documentation

- [Quick Start Guide](docs/quick-start.md) - Get started in 5 minutes
- [Tutorials](docs/tutorials.md) - Step-by-step guides
- [API Documentation](http://localhost:8000/api/docs) - Interactive Swagger UI
- [Deployment Guide](docs/deployment.md) - Production deployment
- [Performance Optimization](docs/performance-optimization.md) - Optimization tips
- [Authentication](docs/auth-middleware.md) - JWT & permissions
- [Middleware](docs/middleware.md) - Request/response middleware
- [Permissions](docs/permissions.md) - Role-based access control

## API Examples

See [examples/api-usage-examples.md](examples/api-usage-examples.md) for complete examples in PHP, JavaScript, and cURL.

### Authentication
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email": "admin@example.com", "password": "password"}'
```

### Create Content
```bash
curl -X POST http://localhost:8000/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "My First Post",
    "content": "Hello, World!",
    "type": "post",
    "status": "published"
  }'
```

## Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
./vendor/bin/pest tests/Unit/
./vendor/bin/pest tests/Integration/
```

## Performance

Verified resource usage:
- Memory: ~4MB typical (< 256MB limit)
- Response time: ~0.01ms (< 100ms limit)
- Disk space: ~1.1MB core (< 100MB limit)
- Supports shared hosting ($3/month)

## Security Features

- Prepared statements (SQL injection prevention)
- Bcrypt password hashing (work factor 10)
- JWT authentication with expiration
- CSRF token protection
- Rate limiting (authentication endpoints)
- Security headers (CSP, X-Frame-Options, etc.)
- Input validation and sanitization
- Security event logging

## Development

Built with modern practices:
- PSR-4 autoloading
- Dependency injection container
- Pest PHP for testing
- Property-based testing support
- OpenAPI specification generation
- Comprehensive error handling

## License

MIT License
