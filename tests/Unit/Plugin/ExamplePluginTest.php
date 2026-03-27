<?php

/**
 * Example Plugin Tests
 * 
 * Tests for the example plugin demonstrating plugin capabilities
 */

declare(strict_types=1);

use ChimeraNoWP\Plugin\PluginManager;
use ChimeraNoWP\Plugin\HookSystem;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\Router;

// Load the example plugin
require_once __DIR__ . '/../../../plugins/example-plugin/example-plugin.php';

use Plugins\ExamplePlugin\ExamplePlugin;

beforeEach(function () {
    $this->container = new Container();
    $this->hooks = new HookSystem();
    $this->router = new Router();
    $this->pluginsPath = __DIR__ . '/../../../plugins';
    
    $this->manager = new PluginManager(
        $this->container,
        $this->hooks,
        $this->pluginsPath,
        $this->router
    );
    
    // Register plugin manager in container so plugin can access it
    $this->container->singleton(PluginManager::class, fn() => $this->manager);
});

it('loads example plugin successfully', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    
    expect($plugin)->toBeInstanceOf(ExamplePlugin::class)
        ->and($plugin->getName())->toBe('Example Plugin')
        ->and($plugin->getVersion())->toBe('1.0.0')
        ->and($plugin->getDependencies())->toBeEmpty();
});

it('registers custom routes during activation', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    
    $this->manager->activatePlugin('example-plugin');
    
    $routes = $this->router->getRoutes();
    expect($routes)->toHaveCount(2);
});

it('registers hello endpoint', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    $routes = $this->router->getRoutes();
    $helloRoute = null;
    
    foreach ($routes as $route) {
        if (str_contains($route->getPath(), '/api/example/hello')) {
            $helloRoute = $route;
            break;
        }
    }
    
    expect($helloRoute)->not->toBeNull()
        ->and($helloRoute->getMethod())->toBe('GET');
});

it('registers process endpoint', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    $routes = $this->router->getRoutes();
    $processRoute = null;
    
    foreach ($routes as $route) {
        if (str_contains($route->getPath(), '/api/example/process')) {
            $processRoute = $route;
            break;
        }
    }
    
    expect($processRoute)->not->toBeNull()
        ->and($processRoute->getMethod())->toBe('POST');
});

it('adds action hooks during boot', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    // Trigger content.created action
    $contentCreated = false;
    $this->hooks->addAction('content.created', function () use (&$contentCreated) {
        $contentCreated = true;
    });
    
    $this->hooks->doAction('content.created', (object)['id' => 1, 'title' => 'Test']);
    
    expect($contentCreated)->toBeTrue();
});

it('adds filter hooks during boot', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    // Apply content.data filter
    $data = ['id' => 1, 'title' => 'Test'];
    $filtered = $this->hooks->applyFilters('content.data', $data);
    
    expect($filtered)->toHaveKey('_plugin_processed')
        ->and($filtered['_plugin_processed'])->toBeTrue()
        ->and($filtered)->toHaveKey('_plugin_name')
        ->and($filtered['_plugin_name'])->toBe('Example Plugin');
});

it('handles content created action', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    // This should not throw an error
    $content = (object)[
        'id' => 123,
        'title' => 'Test Content'
    ];
    
    $this->hooks->doAction('content.created', $content);
    
    expect(true)->toBeTrue(); // If we get here, no error was thrown
});

it('handles content updated action', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    // This should not throw an error
    $content = (object)[
        'id' => 123,
        'title' => 'Updated Content'
    ];
    
    $this->hooks->doAction('content.updated', $content);
    
    expect(true)->toBeTrue(); // If we get here, no error was thrown
});

it('deactivates cleanly', function () {
    $plugin = new ExamplePlugin($this->container, $this->hooks);
    $this->manager->registerPlugin('example-plugin', $plugin);
    $this->manager->activatePlugin('example-plugin');
    
    $result = $this->manager->deactivatePlugin('example-plugin');
    
    expect($result)->toBeTrue()
        ->and($this->manager->isActive('example-plugin'))->toBeFalse();
});
