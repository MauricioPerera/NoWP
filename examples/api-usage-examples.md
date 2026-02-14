# API Usage Examples

Complete examples for common use cases with the WordPress Alternative Framework API.

## Authentication

### Login

```bash
curl -X POST http://localhost/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'
```

Response:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "email": "admin@example.com",
    "displayName": "Admin User",
    "role": "admin"
  }
}
```

### Refresh Token

```bash
curl -X POST http://localhost/api/auth/refresh \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Get Current User

```bash
curl -X GET http://localhost/api/auth/me \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

## Content Management

### Create a Blog Post

```bash
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Getting Started with the Framework",
    "slug": "getting-started",
    "content": "<p>This is my first blog post...</p>",
    "type": "post",
    "status": "published",
    "customFields": {
      "featured": true,
      "readTime": 5,
      "category": "Tutorial"
    }
  }'
```

### List All Posts

```bash
curl -X GET 'http://localhost/api/contents?type=post&status=published&limit=10&page=1' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Get Single Post

```bash
curl -X GET http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Update Post

```bash
curl -X PUT http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Updated Title",
    "content": "<p>Updated content...</p>"
  }'
```

### Delete Post

```bash
curl -X DELETE http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Create a Page

```bash
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "About Us",
    "slug": "about",
    "content": "<p>About our company...</p>",
    "type": "page",
    "status": "published"
  }'
```

## Media Management

### Upload Image

```bash
curl -X POST http://localhost/api/media \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -F 'file=@/path/to/image.jpg'
```

Response:
```json
{
  "id": 1,
  "filename": "image-abc123.jpg",
  "path": "2026/01/image-abc123.jpg",
  "url": "http://localhost/uploads/2026/01/image-abc123.jpg",
  "mimeType": "image/jpeg",
  "size": 245678,
  "width": 1920,
  "height": 1080,
  "thumbnails": {
    "150x150": "http://localhost/uploads/2026/01/image-abc123-150x150.jpg",
    "300x300": "http://localhost/uploads/2026/01/image-abc123-300x300.jpg",
    "1024x1024": "http://localhost/uploads/2026/01/image-abc123-1024x1024.jpg"
  }
}
```

### List Media

```bash
curl -X GET 'http://localhost/api/media?limit=20&page=1' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Delete Media

```bash
curl -X DELETE http://localhost/api/media/1 \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

## User Management

### Create User

```bash
curl -X POST http://localhost/api/users \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "newuser@example.com",
    "password": "secure-password",
    "displayName": "New User",
    "role": "author"
  }'
```

### List Users

```bash
curl -X GET 'http://localhost/api/users?limit=20' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Update User

```bash
curl -X PUT http://localhost/api/users/2 \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "displayName": "Updated Name",
    "role": "editor"
  }'
```

### Delete User

```bash
curl -X DELETE http://localhost/api/users/2 \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

## Multi-Language Content

### Create Content in Multiple Languages

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

# Create Spanish version (linked to English)
curl -X POST http://localhost/api/contents \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "title": "Bienvenido",
    "content": "Bienvenido a nuestro sitio",
    "type": "page",
    "status": "published",
    "locale": "es",
    "translationGroup": "welcome-page"
  }'
```

### Request Content in Specific Language

```bash
# Using query parameter
curl -X GET 'http://localhost/api/contents/1?locale=es' \
  -H 'Authorization: Bearer YOUR_TOKEN'

# Using Accept-Language header
curl -X GET http://localhost/api/contents/1 \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Accept-Language: es'
```

## Search and Filtering

### Filter by Type and Status

```bash
curl -X GET 'http://localhost/api/contents?type=post&status=published' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Filter by Author

```bash
curl -X GET 'http://localhost/api/contents?author_id=1' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Sort Results

```bash
curl -X GET 'http://localhost/api/contents?order_by=created_at&order_direction=desc' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

### Pagination

```bash
curl -X GET 'http://localhost/api/contents?limit=10&offset=20' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

## JavaScript/TypeScript Examples

### Using Fetch API

```javascript
// Login
async function login(email, password) {
  const response = await fetch('http://localhost/api/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });
  
  const data = await response.json();
  localStorage.setItem('token', data.token);
  return data;
}

// Create Post
async function createPost(title, content) {
  const token = localStorage.getItem('token');
  
  const response = await fetch('http://localhost/api/contents', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      title,
      content,
      type: 'post',
      status: 'published',
    }),
  });
  
  return await response.json();
}

// Upload Image
async function uploadImage(file) {
  const token = localStorage.getItem('token');
  const formData = new FormData();
  formData.append('file', file);
  
  const response = await fetch('http://localhost/api/media', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
    body: formData,
  });
  
  return await response.json();
}

// List Posts
async function listPosts(page = 1, limit = 10) {
  const token = localStorage.getItem('token');
  
  const response = await fetch(
    `http://localhost/api/contents?type=post&limit=${limit}&page=${page}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    }
  );
  
  return await response.json();
}
```

### Using Axios

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost/api',
});

// Add token to all requests
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Login
async function login(email, password) {
  const { data } = await api.post('/auth/login', { email, password });
  localStorage.setItem('token', data.token);
  return data;
}

// Create Post
async function createPost(postData) {
  const { data } = await api.post('/contents', postData);
  return data;
}

// Get Posts
async function getPosts(params = {}) {
  const { data } = await api.get('/contents', { params });
  return data;
}
```

## PHP Examples

### Using Guzzle

```php
<?php

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost/api',
]);

// Login
$response = $client->post('/auth/login', [
    'json' => [
        'email' => 'admin@example.com',
        'password' => 'password',
    ],
]);

$data = json_decode($response->getBody(), true);
$token = $data['token'];

// Create Post
$response = $client->post('/contents', [
    'headers' => [
        'Authorization' => "Bearer {$token}",
    ],
    'json' => [
        'title' => 'My Post',
        'content' => 'Post content...',
        'type' => 'post',
        'status' => 'published',
    ],
]);

$post = json_decode($response->getBody(), true);

// Get Posts
$response = $client->get('/contents', [
    'headers' => [
        'Authorization' => "Bearer {$token}",
    ],
    'query' => [
        'type' => 'post',
        'limit' => 10,
    ],
]);

$posts = json_decode($response->getBody(), true);
```

## Error Handling

### Handle API Errors

```javascript
async function apiRequest(url, options) {
  try {
    const response = await fetch(url, options);
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error.message);
    }
    
    return await response.json();
  } catch (error) {
    console.error('API Error:', error.message);
    throw error;
  }
}

// Usage
try {
  const post = await apiRequest('http://localhost/api/contents/1', {
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });
  console.log(post);
} catch (error) {
  alert('Failed to load post: ' + error.message);
}
```

## Rate Limiting

The API implements rate limiting on authentication endpoints:
- **Limit**: 5 requests per 15 minutes per IP
- **Response**: 429 Too Many Requests

```javascript
async function loginWithRetry(email, password, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await login(email, password);
    } catch (error) {
      if (error.status === 429) {
        const retryAfter = error.headers.get('Retry-After');
        await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
        continue;
      }
      throw error;
    }
  }
  throw new Error('Max retries exceeded');
}
```

## Webhooks (Future Feature)

Coming soon: Webhook support for real-time notifications.

```javascript
// Subscribe to content.created event
await api.post('/webhooks', {
  url: 'https://your-app.com/webhook',
  events: ['content.created', 'content.updated'],
});
```

## More Examples

For more examples, see:
- [Quick Start Guide](../docs/quick-start.md)
- [Tutorials](../docs/tutorials.md)
- [API Documentation](http://localhost/api/docs)
