<?php

/**
 * Plugin Interface
 * 
 * Defines the contract that all plugins must implement.
 * Provides lifecycle hooks for plugin initialization and cleanup.
 * 
 * Requirements: 4.1, 4.2
 */

declare(strict_types=1);

namespace ChimeraNoWP\Plugin;

interface PluginInterface
{
    /**
     * Register plugin services and bindings
     * 
     * Called when the plugin is first loaded, before boot().
     * Use this to register services in the container, define routes, etc.
     * 
     * @return void
     */
    public function register(): void;
    
    /**
     * Boot the plugin
     * 
     * Called after all plugins have been registered.
     * Use this to add hooks, filters, and perform initialization that
     * depends on other plugins or services being available.
     * 
     * @return void
     */
    public function boot(): void;
    
    /**
     * Deactivate the plugin
     * 
     * Called when the plugin is being deactivated.
     * Use this to clean up resources, remove hooks, etc.
     * 
     * @return void
     */
    public function deactivate(): void;
    
    /**
     * Get plugin name
     * 
     * @return string
     */
    public function getName(): string;
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function getVersion(): string;
    
    /**
     * Get plugin dependencies
     * 
     * Returns an array of plugin names that this plugin depends on.
     * 
     * @return array<string>
     */
    public function getDependencies(): array;
}
