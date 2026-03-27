<?php

/**
 * Plugin Manager Unit Tests
 * 
 * Tests for PluginManager including plugin loading, activation,
 * deactivation, dependency validation, and error isolation.
 */

declare(strict_types=1);

use ChimeraNoWP\Plugin\PluginManager;
use ChimeraNoWP\Plugin\PluginInterface;
use ChimeraNoWP\Plugin\HookSystem;
use ChimeraNoWP\Core\Container;

beforeEach(function () {
    $this->container = new Container();
    $this->hooks = new HookSystem();
    $this->pluginsPath = __DIR__ . '/../../fixtures/plugins';
    
    $this->manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath
    );
});

// Mock plugin for testing
class TestPlugin implements PluginInterface
{
    public bool $registered = false;
    public bool $booted = false;
    public bool $deactivated = false;
    
    public function __construct(
        private Container $container,
        private HookSystem $hooks
    ) {}
    
    public function register(): void
    {
        $this->registered = true;
    }
    
    public function boot(): void
    {
        $this->booted = true;
    }
    
    public function deactivate(): void
    {
        $this->deactivated = true;
    }
    
    public function getName(): string
    {
        return 'Test Plugin';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDependencies(): array
    {
        return [];
    }
}

class PluginWithDependencies implements PluginInterface
{
    public function __construct(
        private Container $container,
        private HookSystem $hooks
    ) {}
    
    public function register(): void {}
    public function boot(): void {}
    public function deactivate(): void {}
    
    public function getName(): string
    {
        return 'Plugin With Dependencies';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDependencies(): array
    {
        return ['test-plugin'];
    }
}

class FailingPlugin implements PluginInterface
{
    public function __construct(
        private Container $container,
        private HookSystem $hooks
    ) {}
    
    public function register(): void
    {
        throw new \RuntimeException('Registration failed');
    }
    
    public function boot(): void {}
    public function deactivate(): void {}
    
    public function getName(): string
    {
        return 'Failing Plugin';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDependencies(): array
    {
        return [];
    }
}

it('registers a plugin programmatically', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    
    $this->manager->registerPlugin('test-plugin', $plugin);
    
    $plugins = $this->manager->getPlugins();
    expect($plugins)->toHaveKey('test-plugin')
        ->and($plugins['test-plugin'])->toBe($plugin);
});

it('activates a plugin successfully', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    
    $result = $this->manager->activatePlugin('test-plugin');
    
    expect($result)->toBeTrue()
        ->and($plugin->registered)->toBeTrue()
        ->and($plugin->booted)->toBeTrue()
        ->and($this->manager->isActive('test-plugin'))->toBeTrue();
});

it('returns false when activating non-existent plugin', function () {
    $result = $this->manager->activatePlugin('non-existent');
    
    expect($result)->toBeFalse();
});

it('does not activate plugin twice', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    
    $this->manager->activatePlugin('test-plugin');
    $result = $this->manager->activatePlugin('test-plugin');
    
    expect($result)->toBeTrue()
        ->and($this->manager->getActivePlugins())->toHaveCount(1);
});

it('deactivates a plugin successfully', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    $this->manager->activatePlugin('test-plugin');
    
    $result = $this->manager->deactivatePlugin('test-plugin');
    
    expect($result)->toBeTrue()
        ->and($plugin->deactivated)->toBeTrue()
        ->and($this->manager->isActive('test-plugin'))->toBeFalse();
});

it('returns true when deactivating already inactive plugin', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    
    $result = $this->manager->deactivatePlugin('test-plugin');
    
    expect($result)->toBeTrue();
});

it('validates plugin dependencies', function () {
    $plugin1 = new TestPlugin($this->container, $this->hooks);
    $plugin2 = new PluginWithDependencies($this->container, $this->hooks);
    
    $this->manager->registerPlugin('test-plugin', $plugin1);
    $this->manager->registerPlugin('dependent-plugin', $plugin2);
    
    // Try to activate dependent plugin without dependency
    $result = $this->manager->activatePlugin('dependent-plugin');
    expect($result)->toBeFalse();
    
    // Activate dependency first
    $this->manager->activatePlugin('test-plugin');
    
    // Now dependent plugin should activate
    $result = $this->manager->activatePlugin('dependent-plugin');
    expect($result)->toBeTrue();
});

it('isolates plugin errors during activation', function () {
    $plugin = new FailingPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('failing-plugin', $plugin);
    
    $result = $this->manager->activatePlugin('failing-plugin');
    
    expect($result)->toBeFalse()
        ->and($this->manager->isActive('failing-plugin'))->toBeFalse();
    
    $errors = $this->manager->getPluginErrors('failing-plugin');
    expect($errors)->not->toBeEmpty()
        ->and($errors[0]['message'])->toContain('Failed to activate');
});

it('gets all active plugins', function () {
    $plugin1 = new TestPlugin($this->container, $this->hooks);
    $plugin2 = new TestPlugin($this->container, $this->hooks);
    
    $this->manager->registerPlugin('plugin-1', $plugin1);
    $this->manager->registerPlugin('plugin-2', $plugin2);
    
    $this->manager->activatePlugin('plugin-1');
    $this->manager->activatePlugin('plugin-2');
    
    $active = $this->manager->getActivePlugins();
    
    expect($active)->toHaveCount(2)
        ->and($active)->toContain('plugin-1')
        ->and($active)->toContain('plugin-2');
});

it('gets a specific plugin', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    
    $retrieved = $this->manager->getPlugin('test-plugin');
    
    expect($retrieved)->toBe($plugin);
});

it('returns null for non-existent plugin', function () {
    $plugin = $this->manager->getPlugin('non-existent');
    
    expect($plugin)->toBeNull();
});

it('checks if plugin is active', function () {
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    
    expect($this->manager->isActive('test-plugin'))->toBeFalse();
    
    $this->manager->activatePlugin('test-plugin');
    
    expect($this->manager->isActive('test-plugin'))->toBeTrue();
});

it('fires plugin.activated hook', function () {
    $hookFired = false;
    $capturedName = null;
    
    $this->hooks->addAction('plugin.activated', function ($name) use (&$hookFired, &$capturedName) {
        $hookFired = true;
        $capturedName = $name;
    });
    
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    $this->manager->activatePlugin('test-plugin');
    
    expect($hookFired)->toBeTrue()
        ->and($capturedName)->toBe('test-plugin');
});

it('fires plugin.deactivated hook', function () {
    $hookFired = false;
    
    $this->hooks->addAction('plugin.deactivated', function () use (&$hookFired) {
        $hookFired = true;
    });
    
    $plugin = new TestPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('test-plugin', $plugin);
    $this->manager->activatePlugin('test-plugin');
    $this->manager->deactivatePlugin('test-plugin');
    
    expect($hookFired)->toBeTrue();
});

it('fires plugin.error hook on error', function () {
    $hookFired = false;
    $capturedMessage = null;
    
    $this->hooks->addAction('plugin.error', function ($name, $message) use (&$hookFired, &$capturedMessage) {
        $hookFired = true;
        $capturedMessage = $message;
    });
    
    $plugin = new FailingPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('failing-plugin', $plugin);
    $this->manager->activatePlugin('failing-plugin');
    
    expect($hookFired)->toBeTrue()
        ->and($capturedMessage)->toContain('Failed to activate');
});

it('tracks multiple errors for a plugin', function () {
    $plugin = new FailingPlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('failing-plugin', $plugin);
    
    $this->manager->activatePlugin('failing-plugin');
    $this->manager->activatePlugin('failing-plugin');
    
    $errors = $this->manager->getPluginErrors('failing-plugin');
    expect($errors)->toHaveCount(2);
});

it('gets all errors from all plugins', function () {
    $plugin1 = new FailingPlugin($this->container, $this->hooks);
    $plugin2 = new FailingPlugin($this->container, $this->hooks);
    
    $this->manager->registerPlugin('plugin-1', $plugin1);
    $this->manager->registerPlugin('plugin-2', $plugin2);
    
    $this->manager->activatePlugin('plugin-1');
    $this->manager->activatePlugin('plugin-2');
    
    $errors = $this->manager->getErrors();
    expect($errors)->toHaveKey('plugin-1')
        ->and($errors)->toHaveKey('plugin-2');
});

it('continues operating after plugin error', function () {
    $failingPlugin = new FailingPlugin($this->container, $this->hooks);
    $workingPlugin = new TestPlugin($this->container, $this->hooks);
    
    $this->manager->registerPlugin('failing-plugin', $failingPlugin);
    $this->manager->registerPlugin('working-plugin', $workingPlugin);
    
    $this->manager->activatePlugin('failing-plugin');
    $result = $this->manager->activatePlugin('working-plugin');
    
    expect($result)->toBeTrue()
        ->and($this->manager->isActive('working-plugin'))->toBeTrue()
        ->and($this->manager->isActive('failing-plugin'))->toBeFalse();
});

it('handles deactivation errors gracefully', function () {
    $plugin = new class($this->container, $this->hooks) implements PluginInterface {
        public function __construct(
            private Container $container,
            private HookSystem $hooks
        ) {}
        
        public function register(): void {}
        public function boot(): void {}
        
        public function deactivate(): void
        {
            throw new \RuntimeException('Deactivation failed');
        }
        
        public function getName(): string { return 'Test'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDependencies(): array { return []; }
    };
    
    $this->manager->registerPlugin('test-plugin', $plugin);
    $this->manager->activatePlugin('test-plugin');
    
    $result = $this->manager->deactivatePlugin('test-plugin');
    
    expect($result)->toBeFalse();
    
    $errors = $this->manager->getPluginErrors('test-plugin');
    expect($errors)->not->toBeEmpty();
});

// Tests for plugin route registration

it('registers custom routes for plugins', function () {
    $router = new ChimeraNoWP\Core\Router();
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath,
        $router
    );
    
    $route = $manager->registerRoute('GET', '/api/custom', function () {
        return 'custom response';
    });
    
    expect($route)->toBeInstanceOf(ChimeraNoWP\Core\Route::class);
    
    $routes = $router->getRoutes();
    expect($routes)->toHaveCount(1);
});

it('supports all HTTP methods for route registration', function () {
    $router = new ChimeraNoWP\Core\Router();
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath,
        $router
    );
    
    $manager->registerRoute('GET', '/api/get', fn() => 'get');
    $manager->registerRoute('POST', '/api/post', fn() => 'post');
    $manager->registerRoute('PUT', '/api/put', fn() => 'put');
    $manager->registerRoute('DELETE', '/api/delete', fn() => 'delete');
    $manager->registerRoute('PATCH', '/api/patch', fn() => 'patch');
    
    $routes = $router->getRoutes();
    expect($routes)->toHaveCount(5);
});

it('returns null when registering route without router', function () {
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath
    );
    
    $route = $manager->registerRoute('GET', '/api/test', fn() => 'test');
    
    expect($route)->toBeNull();
});

it('returns null for unsupported HTTP method', function () {
    $router = new ChimeraNoWP\Core\Router();
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath,
        $router
    );
    
    $route = $manager->registerRoute('INVALID', '/api/test', fn() => 'test');
    
    expect($route)->toBeNull();
});

it('provides router access to plugins', function () {
    $router = new ChimeraNoWP\Core\Router();
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath,
        $router
    );
    
    $retrievedRouter = $manager->getRouter();
    
    expect($retrievedRouter)->toBe($router);
});

it('returns null when no router is configured', function () {
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath
    );
    
    $router = $manager->getRouter();
    
    expect($router)->toBeNull();
});

it('allows plugins to register routes during activation', function () {
    $router = new ChimeraNoWP\Core\Router();
    $manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath,
        $router
    );
    
    $plugin = new class($this->container, $this->hooks, $manager) implements PluginInterface {
        public function __construct(
            private Container $container,
            private HookSystem $hooks,
            private PluginManager $manager
        ) {}
        
        public function register(): void
        {
            // Register custom route
            $this->manager->registerRoute('GET', '/api/plugin/custom', function () {
                return ['message' => 'Plugin route'];
            });
        }
        
        public function boot(): void {}
        public function deactivate(): void {}
        public function getName(): string { return 'Route Plugin'; }
        public function getVersion(): string { return '1.0.0'; }
        public function getDependencies(): array { return []; }
    };
    
    $manager->registerPlugin('route-plugin', $plugin);
    $manager->activatePlugin('route-plugin');
    
    $routes = $router->getRoutes();
    expect($routes)->toHaveCount(1);
});
