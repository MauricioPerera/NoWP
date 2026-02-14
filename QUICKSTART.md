# NoWP Framework - Quick Start Guide

Get your NoWP installation up and running in 5 minutes!

## Prerequisites

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- Apache with mod_rewrite (or nginx)

## Installation Steps

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your settings:

```env
# Application
APP_NAME="NoWP"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nowp
DB_USERNAME=root
DB_PASSWORD=

# JWT
JWT_SECRET=your-secret-key-here
JWT_EXPIRATION=3600

# Cache
CACHE_DRIVER=file
```

### 3. Create Database

```bash
mysql -u root -p
CREATE DATABASE nowp;
exit;
```

### 4. Run Migrations

```bash
php cli/migrate.php
```

This will create all necessary tables.

### 5. Check System Requirements

```bash
php cli/check-resources.php
```

This verifies your system meets all requirements.

### 6. Start Development Server

```bash
php -S localhost:8000 -t public/
```

Your API is now running at `http://localhost:8000`!

## First Steps

### 1. View API Documentation

Open your browser and visit:
```
http://localhost:8000/api/docs
```

You'll see interactive Swagger UI documentation for all API endpoints.

### 2. Create Admin User

You can create an admin user via the API:

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@example.com",
    "password": "secure-password",
    "name": "Admin User"
  }'
```

Then update the user role in the database:
```sql
UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';
```

### 3. Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@example.com",
    "password": "secure-password"
  }'
```

Save the returned token for authenticated requests.

### 4. Create Your First Post

```bash
curl -X POST http://localhost:8000/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "My First Post",
    "content": "Hello, NoWP!",
    "type": "post",
    "status": "published"
  }'
```

## Using the Admin Panel

### 1. Install Admin Panel Dependencies

```bash
cd admin
npm install
```

### 2. Start Admin Panel Dev Server

```bash
npm run dev
```

The admin panel will be available at `http://localhost:3000`

### 3. Login to Admin Panel

- Open `http://localhost:3000`
- Login with your admin credentials
- Explore the dashboard, content management, media library, and more!

## Using the TypeScript Client

### 1. Install Client

```bash
cd client
npm install
```

### 2. Build Client

```bash
npm run build
```

### 3. Use in Your Project

```typescript
import { APIClient } from '@nowp/client';

const client = new APIClient({
  baseURL: 'http://localhost:8000',
});

// Login
await client.auth.login('admin@example.com', 'password');

// Get posts
const posts = await client.content.list({ type: 'post' });
console.log(posts);
```

## Next Steps

- Read the [Tutorials](docs/tutorials.md) for detailed guides
- Explore [API Examples](examples/api-usage-examples.md)
- Learn about [Authentication & Permissions](docs/auth-middleware.md)
- Check [Deployment Guide](docs/deployment.md) for production setup
- Review [Performance Optimization](docs/performance-optimization.md)

## Common Issues

### Port Already in Use

If port 8000 is already in use, specify a different port:
```bash
php -S localhost:8080 -t public/
```

### Database Connection Failed

- Verify MySQL is running
- Check database credentials in `.env`
- Ensure the database exists

### Permission Denied

On Linux/Mac, you may need to set permissions:
```bash
chmod -R 755 storage/
chmod -R 755 public/uploads/
```

### Missing PHP Extensions

Install required extensions:
```bash
# Ubuntu/Debian
sudo apt-get install php8.1-pdo php8.1-mysql php8.1-mbstring php8.1-json

# macOS (Homebrew)
brew install php@8.1
```

## Getting Help

- Check the [Documentation](docs/)
- Review [API Documentation](http://localhost:8000/api/docs)
- Look at [Examples](examples/)

## What's Next?

Now that you have NoWP running:

1. **Create Content**: Use the admin panel or API to create posts and pages
2. **Upload Media**: Try uploading images through the media library
3. **Manage Users**: Create users with different roles
4. **Build a Plugin**: Extend functionality with custom plugins
5. **Create a Theme**: Design your own frontend theme
6. **Deploy**: Follow the deployment guide for production

Happy building! 🚀
