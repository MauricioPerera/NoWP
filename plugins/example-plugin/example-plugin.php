<?php

/**
 * Example Plugin
 * 
 * Demonstrates how to create a plugin for the WordPress Alternative Framework.
 * Shows usage of hooks, filters, and custom endpoint registration.
 * 
 * Plugin Name: Example Plugin
 * Plugin Version: 1.0.0
 * Description: A simple example plugin demonstrating framework capabilities
 * Author: Framework Team
 */

declare(strict_types=1);

namespace Plugins\ExamplePlugin;

use ChimeraNoWP\Plugin\PluginInterface;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Plugin\HookSystem;
use ChimeraNoWP\Plugin\PluginManager;

class ExamplePlugin implements PluginInterface
{
    private Container $container;
    private HookSystem $hooks;
    private PluginManager $pluginManager;
    
    public function __construct(Container $container, HookSystem $hooks)
    {
        $this->container = $container;
        $this->hooks = $hooks;
        
        // Get plugin manager from container if available
        if ($container->has(PluginManager::class)) {
            $this->pluginManager = $container->resolve(PluginManager::class);
        }
    }
    
    /**
     * Register plugin services and routes
     */
    public function register(): void
    {
        // Register custom API endpoints
        if (isset($this->pluginManager)) {
            $this->registerRoutes();
        }
    }
    
    /**
     * Boot the plugin and add hooks
     */
    public function boot(): void
    {
        // Add action hooks
        $this->hooks->addAction('content.created', [$this, 'onContentCreated'], 10);
        $this->hooks->addAction('content.updated', [$this, 'onContentUpdated'], 10);
        
        // Add filter hooks
        $this->hooks->addFilter('content.data', [$this, 'filterContentData'], 10);
    }
    
    /**
     * Deactivate the plugin
     */
    public function deactivate(): void
    {
        // Clean up resources if needed
        // In this example, hooks are automatically removed by the system
    }
    
    /**
     * Get plugin name
     */
    public function getName(): string
    {
        return 'Example Plugin';
    }
    
    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array
    {
        // This plugin has no dependencies
        return [];
    }
    
    /**
     * Register custom routes
     */
    private function registerRoutes(): void
    {
        // Register a simple GET endpoint
        $this->pluginManager->registerRoute('GET', '/api/example/hello', function () {
            return [
                'message' => 'Hello from Example Plugin!',
                'version' => $this->getVersion(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        });
        
        // Register a POST endpoint with data processing
        $this->pluginManager->registerRoute('POST', '/api/example/process', function ($request) {
            $data = $request->getBody();
            
            return [
                'success' => true,
                'processed' => true,
                'data' => $data,
                'message' => 'Data processed by Example Plugin'
            ];
        });
    }
    
    /**
     * Action hook: Called when content is created
     */
    public function onContentCreated($content): void
    {
        // Example: Log content creation
        error_log(sprintf(
            '[Example Plugin] Content created: ID=%s, Title=%s',
            $content->id ?? 'unknown',
            $content->title ?? 'untitled'
        ));
    }
    
    /**
     * Action hook: Called when content is updated
     */
    public function onContentUpdated($content): void
    {
        // Example: Log content update
        error_log(sprintf(
            '[Example Plugin] Content updated: ID=%s',
            $content->id ?? 'unknown'
        ));
    }
    
    /**
     * Filter hook: Modify content data before returning
     */
    public function filterContentData($data): array
    {
        // Example: Add custom field to all content responses
        if (is_array($data)) {
            $data['_plugin_processed'] = true;
            $data['_plugin_name'] = $this->getName();
        }
        
        return $data;
    }
}
