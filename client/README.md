# NoWP Framework API Client

TypeScript/JavaScript client for NoWP Framework API with automatic token management, retry logic, and full type safety.

## Features

- 🔐 Automatic JWT token management
- 🔄 Automatic retry with exponential backoff
- 📦 Works in Node.js and browsers
- 🎯 Full TypeScript support
- 🚀 Lightweight and tree-shakeable

## Installation

```bash
npm install @nowp/client
```

## Quick Start

```typescript
import { APIClient } from '@nowp/client';

const client = new APIClient({
  baseURL: 'https://your-site.com',
  onTokenRefresh: (token) => {
    // Store token in localStorage, cookies, etc.
    localStorage.setItem('token', token);
  }
});

// Login
const { token } = await client.auth.login('user@example.com', 'password');

// Get content
const posts = await client.content.list({ type: 'post', status: 'published' });

// Create content
const newPost = await client.content.create({
  title: 'My First Post',
  content: 'Hello, world!',
  type: 'post',
  status: 'published'
});
```

## Authentication

```typescript
// Login
const { token, expiresAt } = await client.auth.login(email, password);

// Register
const user = await client.auth.register(email, password, name);

// Get current user
const me = await client.auth.me();

// Refresh token
const newTokens = await client.auth.refresh(token);

// Logout
await client.auth.logout();
```

## Content Management

```typescript
// List content with filters
const posts = await client.content.list({
  type: 'post',
  status: 'published',
  page: 1,
  perPage: 10
});

// Get single content
const post = await client.content.get(1);
const postBySlug = await client.content.getBySlug('my-post');

// Create content
const newPost = await client.content.create({
  title: 'My Post',
  content: 'Content here',
  type: 'post',
  status: 'draft',
  customFields: {
    featured: true,
    views: 0
  }
});

// Update content
const updated = await client.content.update(1, {
  title: 'Updated Title',
  status: 'published'
});

// Delete content
await client.content.delete(1);

// Get versions
const versions = await client.content.getVersions(1);

// Restore version
const restored = await client.content.restore(1, versionId);
```

## Media Management

```typescript
// Upload file
const file = document.querySelector('input[type="file"]').files[0];
const media = await client.media.upload(file);

// List media
const mediaList = await client.media.list({
  mimeType: 'image/jpeg',
  page: 1,
  perPage: 20
});

// Get media
const mediaItem = await client.media.get(1);

// Delete media
await client.media.delete(1);
```

## User Management

```typescript
// List users
const users = await client.users.list({
  role: 'author',
  page: 1,
  perPage: 10
});

// Get user
const user = await client.users.get(1);

// Create user
const newUser = await client.users.create({
  email: 'user@example.com',
  password: 'secure-password',
  name: 'John Doe',
  role: 'author'
});

// Update user
const updated = await client.users.update(1, {
  name: 'Jane Doe',
  role: 'editor'
});

// Delete user
await client.users.delete(1);
```

## Configuration

```typescript
const client = new APIClient({
  baseURL: 'https://your-site.com',
  token: 'existing-token', // Optional: set initial token
  onTokenRefresh: (token) => {
    // Called when token is refreshed
    localStorage.setItem('token', token);
  },
  maxRetries: 3, // Default: 3
  retryDelay: 1000, // Default: 1000ms
});
```

## Error Handling

```typescript
try {
  const post = await client.content.get(999);
} catch (error) {
  console.error(error.message); // "Content not found"
  console.error(error.status); // 404
  console.error(error.data); // Full error response
}
```

## Abort Requests

```typescript
const controller = new AbortController();

const promise = client.content.list({}, {
  signal: controller.signal
});

// Cancel request
controller.abort();
```

## TypeScript Support

All methods are fully typed with TypeScript:

```typescript
import type { Content, User, Media } from '@nowp/client';

const post: Content = await client.content.get(1);
const user: User = await client.auth.me();
const media: Media = await client.media.get(1);
```

## License

MIT
