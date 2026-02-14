# Tutorials

Step-by-step tutorials for common use cases.

## Tutorial 1: Building a Blog API

Learn how to build a complete blog API with authentication, posts, and comments.

### Step 1: Set Up Authentication

First, create an admin user through the installer or directly in the database.

### Step 2: Create Post Endpoints

The framework already includes content endpoints. Test them:

```bash
# List all posts
curl -X GET http://localhost/api/contents?type=post \
  -H 'Authorization: Bearer YOUR_TOKEN'

# Create a post
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Getting Started with the Framework",
    "slug": "getting-started",
    "content": "This is my first blog post...",
    "type": "post",
    "status": "published"
  }'

# Get a specific post
curl -X GET http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN'

# Update a post
curl -X PUT http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Updated Title",
    "content": "Updated content..."
  }'

# Delete a post
curl -X DELETE http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Step 3: Add Custom Fields

Add metadata to your posts:

```bash
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Post with Metadata",
    "content": "Content here...",
    "type": "post",
    "status": "published",
    "customFields": {
      "featured": true,
      "readTime": 5,
      "category": "Technology"
    }
  }'
```

### Step 4: Upload Featured Images

```bash
curl -X POST http://localhost/api/media \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -F 'file=@/path/to/image.jpg'
```

Response includes the image URL and thumbnails:

```json
{
  "id": 1,
  "filename": "image-abc123.jpg",
  "url": "http://localhost/uploads/2026/01/image-abc123.jpg",
  "thumbnails": {
    "150x150": "http://localhost/uploads/2026/01/image-abc123-150x150.jpg",
    "300x300": "http://localhost/uploads/2026/01/image-abc123-300x300.jpg"
  }
}
```

## Tutorial 2: Creating a Custom Plugin

Build a plugin that adds custom functionality.

### Step 1: Create Plugin Directory

```bash
mkdir plugins/reading-time
```

### Step 2: Create Plugin File

Create `plugins/reading-time/reading-time.php`:

```php
<?php

use Framework\Plugin\PluginInterface;
use Framework\Plugin\HookSystem;

class ReadingTimePlugin implements PluginInterface
{
    private HookSystem $hooks;
    
    public function register(): void
    {
        $this->hooks = app()->resolve(HookSystem::class);
        
        // Add reading time to content
        $this->hooks->addFilter('content.render', [$this, 'addReadingTime'], 10);
    }
    
    public function boot(): void
    {
        // Plugin is active
    }
    
    public function deactivate(): void
    {
        // Cleanup if needed
    }
    
    public function addReadingTime(array $content): array
    {
        $wordCount = str_word_count(strip_tags($content['content']));
        $readingTime = ceil($wordCount / 200); // 200 words per minute
        
        $content['readingTime'] = $readingTime;
        $content['readingTimeText'] = "{$readingTime} min read";
        
        return $content;
    }
}

return new ReadingTimePlugin();
```

### Step 3: Activate Plugin

Activate through the admin panel or directly in the database.

### Step 4: Test Plugin

```bash
curl -X GET http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

Response now includes reading time:

```json
{
  "id": 1,
  "title": "My Post",
  "content": "...",
  "readingTime": 5,
  "readingTimeText": "5 min read"
}
```

## Tutorial 3: Implementing Multi-Language Content

Add internationalization to your content.

### Step 1: Create Content in Multiple Languages

```bash
# Create English version
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Welcome",
    "content": "Welcome to our site",
    "type": "page",
    "status": "published",
    "locale": "en"
  }'

# Create Spanish version
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Bienvenido",
    "content": "Bienvenido a nuestro sitio",
    "type": "page",
    "status": "published",
    "locale": "es",
    "parentId": 1
  }'
```

### Step 2: Request Content in Specific Language

```bash
# Get Spanish version
curl -X GET http://localhost/api/contents/1?locale=es \
  -H 'Authorization: Bearer YOUR_TOKEN'

# Or use Accept-Language header
curl -X GET http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept-Language: es'
```

## Tutorial 4: Setting Up Caching

Improve performance with caching.

### Step 1: Configure Cache

In `config/cache.php`:

```php
return [
    'default' => 'apcu', // or 'redis', 'memcached', 'file'
    'ttl' => 3600, // 1 hour
    
    'stores' => [
        'apcu' => [
            'driver' => 'apcu',
        ],
        'file' => [
            'driver' => 'file',
            'path' => BASE_PATH . '/storage/cache',
        ],
    ],
];
```

### Step 2: Use Cache in Your Code

```php
$cache = app()->resolve(CacheManager::class);

// Cache content for 1 hour
$content = $cache->remember('content:1', 3600, function() {
    return $contentRepository->find(1);
});

// Invalidate cache when content updates
$cache->delete('content:1');
```

### Step 3: Cache Tags

```php
// Tag related cache entries
$cache->tags(['content', 'post:1'])->remember('post:1:full', 3600, function() {
    return $this->getFullPost(1);
});

// Invalidate all content cache
$cache->tags(['content'])->flush();
```

## Tutorial 5: Backup and Restore

Protect your data with backups.

### Step 1: Create Backup

```php
use Framework\Backup\BackupCommand;

$backup = new BackupCommand($connection);
$backupFile = $backup->execute([
    'include_files' => true,
]);

echo "Backup created: {$backupFile}";
```

### Step 2: Restore Backup

```php
use Framework\Backup\RestoreCommand;

$restore = new RestoreCommand($connection);
$result = $restore->execute($backupFile, [
    'restore_database' => true,
    'restore_files' => true,
]);

echo "Restore completed!";
```

### Step 3: Migrate URLs

When moving to a new domain:

```php
use Framework\Backup\MigrateCommand;

$migrate = new MigrateCommand($connection);
$stats = $migrate->execute(
    'https://old-site.com',
    'https://new-site.com'
);

echo "Updated {$stats['contents_updated']} content entries";
```

## More Resources

- [API Reference](http://localhost/api/docs)
- [Authentication Guide](./auth-middleware.md)
- [Middleware Documentation](./middleware.md)
- [Plugin Development](./plugins.md)
