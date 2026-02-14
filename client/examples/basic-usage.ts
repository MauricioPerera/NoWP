/**
 * Basic Usage Example
 */

import { APIClient } from '../src';

async function main() {
  // Initialize client
  const client = new APIClient({
    baseURL: 'http://localhost:8000',
    onTokenRefresh: (token) => {
      console.log('Token refreshed:', token);
      // Store in localStorage, cookies, etc.
    },
  });

  try {
    // Login
    console.log('Logging in...');
    const { token } = await client.auth.login('admin@example.com', 'password');
    console.log('Logged in successfully!');

    // Get current user
    const me = await client.auth.me();
    console.log('Current user:', me);

    // List published posts
    console.log('\nFetching published posts...');
    const posts = await client.content.list({
      type: 'post',
      status: 'published',
      page: 1,
      perPage: 5,
    });
    console.log(`Found ${posts.meta.total} posts`);
    posts.data.forEach(post => {
      console.log(`- ${post.title} (${post.slug})`);
    });

    // Create new post
    console.log('\nCreating new post...');
    const newPost = await client.content.create({
      title: 'My API Post',
      content: 'This post was created via the API client!',
      type: 'post',
      status: 'draft',
      customFields: {
        featured: true,
        category: 'Technology',
      },
    });
    console.log('Created post:', newPost.id);

    // Update post
    console.log('\nPublishing post...');
    const updated = await client.content.update(newPost.id, {
      status: 'published',
    });
    console.log('Post published!');

    // Get post by slug
    const retrieved = await client.content.getBySlug(updated.slug);
    console.log('Retrieved post:', retrieved.title);

    // List media
    console.log('\nFetching media...');
    const media = await client.media.list({ page: 1, perPage: 5 });
    console.log(`Found ${media.meta.total} media items`);

    // Logout
    console.log('\nLogging out...');
    await client.auth.logout();
    console.log('Logged out successfully!');

  } catch (error: any) {
    console.error('Error:', error.message);
    if (error.data) {
      console.error('Details:', error.data);
    }
  }
}

main();
