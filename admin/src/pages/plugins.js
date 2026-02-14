import { router } from '../router.js';

router.register('plugins', async () => {
  const container = document.getElementById('page-content');
  
  container.innerHTML = `
    <div class="card">
      <div class="card-header">
        <h1 class="card-title">Plugins</h1>
      </div>

      <p style="color: var(--gray-600); margin-bottom: 20px;">
        Plugins are managed through the file system. Place plugin files in the <code>/plugins</code> directory.
      </p>

      <div class="card" style="background: var(--gray-50);">
        <h3 style="margin-bottom: 10px;">Plugin Development</h3>
        <p style="margin-bottom: 10px;">To create a plugin:</p>
        <ol style="margin-left: 20px; line-height: 1.8;">
          <li>Create a directory in <code>/plugins/your-plugin-name/</code></li>
          <li>Create a main PHP file that implements <code>PluginInterface</code></li>
          <li>Register hooks and filters using the <code>HookSystem</code></li>
          <li>The plugin will be automatically loaded on next request</li>
        </ol>
      </div>

      <div class="card">
        <h3 style="margin-bottom: 15px;">Example Plugin Structure</h3>
        <pre style="background: var(--gray-900); color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>/plugins/
  my-plugin/
    my-plugin.php
    README.md</code></pre>
      </div>

      <div class="card">
        <h3 style="margin-bottom: 15px;">Example Plugin Code</h3>
        <pre style="background: var(--gray-900); color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><code>&lt;?php

namespace MyPlugin;

use NoWP\\Plugin\\PluginInterface;
use NoWP\\Plugin\\HookSystem;
use NoWP\\Core\\Router;

class MyPlugin implements PluginInterface
{
    public function register(): void
    {
        // Register hooks
        HookSystem::addAction('content.created', [$this, 'onContentCreated']);
        HookSystem::addFilter('content.title', [$this, 'modifyTitle']);
    }

    public function boot(Router $router): void
    {
        // Register custom routes
        $router->get('/api/my-plugin/hello', function() {
            return ['message' => 'Hello from plugin!'];
        });
    }

    public function deactivate(): void
    {
        // Cleanup
    }

    public function onContentCreated($content): void
    {
        // Handle content creation
    }

    public function modifyTitle(string $title): string
    {
        return strtoupper($title);
    }
}</code></pre>
      </div>

      <div class="card" style="background: var(--gray-50);">
        <h3 style="margin-bottom: 10px;">Available Hooks</h3>
        <ul style="margin-left: 20px; line-height: 1.8;">
          <li><code>content.created</code> - Fired when content is created</li>
          <li><code>content.updated</code> - Fired when content is updated</li>
          <li><code>content.deleted</code> - Fired when content is deleted</li>
          <li><code>media.uploaded</code> - Fired when media is uploaded</li>
          <li><code>user.created</code> - Fired when user is created</li>
        </ul>
      </div>
    </div>
  `;
});
