# Performance Optimization Guide

This document outlines performance optimizations implemented in the framework and recommendations for optimal performance.

## Implemented Optimizations

### 1. N+1 Query Prevention

**Problem**: Loading custom fields for each content item individually when fetching multiple items.

**Solution**: Implemented eager loading in `ContentRepository::findAll()`:
- Collects all content IDs from the result set
- Loads all custom fields in a single query using `WHERE IN`
- Maps custom fields to their respective content items

**Impact**: Reduces database queries from N+1 to 2 queries (1 for content + 1 for all custom fields).

```php
// Before: N+1 queries (1 + N custom field queries)
$contents = $repository->findAll(['limit' => 20]); // 21 queries

// After: 2 queries (1 for content + 1 for all custom fields)
$contents = $repository->findAll(['limit' => 20]); // 2 queries
```

### 2. Database Indexes

Recommended indexes for optimal query performance:

```sql
-- Contents table
CREATE INDEX idx_contents_type_status ON contents(type, status);
CREATE INDEX idx_contents_author ON contents(author_id);
CREATE INDEX idx_contents_slug ON contents(slug);
CREATE INDEX idx_contents_locale ON contents(locale);
CREATE INDEX idx_contents_created_at ON contents(created_at);

-- Custom fields table
CREATE INDEX idx_custom_fields_content ON custom_fields(content_id);
CREATE INDEX idx_custom_fields_key ON custom_fields(field_key);

-- Media table
CREATE INDEX idx_media_uploaded_by ON media(uploaded_by);
CREATE INDEX idx_media_uploaded_at ON media(uploaded_at);

-- Users table
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
```

### 3. Lazy Loading

The framework uses lazy loading for:
- **Database connections**: Connection established only when first query is executed
- **Plugin loading**: Plugins loaded on-demand
- **Cache adapters**: Cache system initialized only when used

### 4. Prepared Statements

All database queries use prepared statements by default:
- Prevents SQL injection
- Allows query plan caching by database
- Improves performance for repeated queries

### 5. Caching Strategy

**Multi-layer caching**:
1. **APCu** (preferred): In-memory cache, fastest option
2. **Redis/Memcached**: External cache servers
3. **File cache**: Fallback option

**Cache usage**:
```php
// Cache content for 1 hour
$cache->remember('content:' . $id, 3600, function() use ($id) {
    return $this->repository->find($id);
});

// Invalidate on update
$cache->delete('content:' . $id);
```

## Performance Benchmarks

Target performance metrics (on shared hosting):

- **Response time**: < 100ms for API requests
- **Memory usage**: < 256MB per request
- **Disk space**: < 100MB base installation
- **Throughput**: 1000+ requests/hour on $3/month hosting

## Optimization Recommendations

### 1. Enable OPcache

Add to `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### 2. Use APCu for Caching

Install APCu extension:
```bash
pecl install apcu
```

Configure in `config/cache.php`:
```php
'default' => 'apcu',
```

### 3. Optimize Database

**Enable query cache** (MySQL):
```sql
SET GLOBAL query_cache_size = 67108864; -- 64MB
SET GLOBAL query_cache_type = 1;
```

**Add indexes** (see section 2 above)

**Optimize tables regularly**:
```sql
OPTIMIZE TABLE contents, custom_fields, media, users;
```

### 4. Enable Gzip Compression

Add to `.htaccess`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

### 5. Use CDN for Static Assets

Serve images and static files from a CDN:
```php
// config/app.php
'cdn_url' => 'https://cdn.example.com',
```

### 6. Implement HTTP Caching

Add cache headers for static content:
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 7. Limit Result Sets

Always use pagination:
```php
$contents = $repository->findAll([
    'limit' => 20,
    'offset' => 0,
]);
```

### 8. Optimize Images

Use the built-in image processor:
```php
$imageProcessor->generateThumbnails($path, [
    'thumbnail' => [150, 150],
    'medium' => [300, 300],
    'large' => [1024, 1024],
]);
```

### 9. Monitor Performance

Use the built-in logging:
```php
$start = microtime(true);
// ... operation ...
$duration = microtime(true) - $start;

if ($duration > 0.1) { // Log slow operations
    error_log("Slow operation: {$duration}s");
}
```

### 10. Database Connection Pooling

For high-traffic sites, use persistent connections:
```php
// config/database.php
'options' => [
    PDO::ATTR_PERSISTENT => true,
],
```

## Profiling

### Using Xdebug

Install Xdebug and profile slow requests:
```ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_enable_trigger=1
```

### Query Logging

Enable query logging in development:
```php
// Log all queries
$connection->enableQueryLog();

// Get logged queries
$queries = $connection->getQueryLog();
```

## Common Performance Issues

### Issue 1: Slow Content Listing

**Symptom**: `/api/contents` endpoint takes > 500ms

**Solutions**:
1. Add database indexes (see section 2)
2. Reduce page size (limit parameter)
3. Enable caching for list results
4. Use pagination

### Issue 2: High Memory Usage

**Symptom**: PHP memory limit exceeded

**Solutions**:
1. Reduce result set size
2. Use generators for large datasets
3. Clear objects after use
4. Increase PHP memory limit (last resort)

### Issue 3: Slow Image Uploads

**Symptom**: Image upload takes > 5 seconds

**Solutions**:
1. Process thumbnails asynchronously
2. Optimize image before upload (client-side)
3. Use faster storage (SSD)
4. Increase PHP upload limits

## Monitoring Tools

Recommended tools for monitoring:
- **New Relic**: Application performance monitoring
- **Blackfire**: PHP profiler
- **MySQL Slow Query Log**: Identify slow queries
- **Apache/Nginx access logs**: Track response times

## Load Testing

Test your application under load:

```bash
# Using Apache Bench
ab -n 1000 -c 10 http://your-domain.com/api/contents

# Using wrk
wrk -t4 -c100 -d30s http://your-domain.com/api/contents
```

Target metrics:
- **Requests/sec**: > 100
- **Mean response time**: < 100ms
- **Error rate**: < 1%

## Conclusion

Following these optimization guidelines will ensure your application performs well even on budget shared hosting. Regular monitoring and profiling will help identify and resolve performance bottlenecks as they arise.
