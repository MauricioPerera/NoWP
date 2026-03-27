<?php

/**
 * Plugin Manager
 * 
 * Manages plugin loading, activation, deactivation, and dependency resolution.
 * Provides isolation for plugin errors to prevent system-wide failures.
 * 
 * Requirements: 4.1, 4.2, 4.4, 4.5
 */

declare(strict_types=1);

namespace ChimeraNoWP\Plugin;

use ChimeraNoWP\Core\Container;

class PluginManager
{
    private Container $container;
    private HookSystem $hooks;
    private string $pluginsPath;
    
    /**
     * Registered plugins
     * @var array<string, PluginInterface>
     */
    private array $plugins = [];
    
    /**
     * Active plugins
     * @var array<string>
     */
    private array $activePlugins = [];
    
    /**
     * Plugin errors
     * @var array<string, array>
     */
    private array $errors = [];
    
    public function __construct(
        Container $container,
        HookSystem $hooks,
        string $pluginsPath,
        private ?\ChimeraNoWP\Core\Router $router = null
    ) {
        $this->container = $container;
        $this->hooks = $hooks;
        $this->pluginsPath = $pluginsPath;
    }
    
    /**
     * Load all plugins from the plugins directory
     * 
     * @return void
     */
    public function loadPlugins(): void
    {
        if (!is_dir($this->pluginsPath)) {
            return;
        }
        
        $pluginDirs = glob($this->pluginsPath . '/*', GLOB_ONLYDIR);
        
        if (!$pluginDirs) {
            return;
        }
        
        foreach ($pluginDirs as $pluginDir) {
            $this->loadPlugin($pluginDir);
        }
    }
    
    /**
     * Load a single plugin from a directory
     * 
     * @param string $pluginDir Plugin directory path
     * @return void
     */
    private function loadPlugin(string $pluginDir): void
    {
        $pluginName = basename($pluginDir);
        $pluginFile = $pluginDir . '/' . $pluginName . '.php';
        
        if (!file_exists($pluginFile)) {
            $this->logError($pluginName, 'Plugin file not found: ' . $pluginFile);
            return;
        }
        
        try {
            require_once $pluginFile;
            
            // Try to instantiate plugin class
            $className = $this->getPluginClassName($pluginName);
            
            if (!class_exists($className)) {
                $this->logError($pluginName, 'Plugin class not found: ' . $className);
                return;
            }
            
            $plugin = new $className($this->container, $this->hooks);
            
            if (!$plugin instanceof PluginInterface) {
                $this->logError($pluginName, 'Plugin must implement PluginInterface');
                return;
            }
            
            $this->plugins[$pluginName] = $plugin;
        } catch (\Throwable $e) {
            $this->logError($pluginName, 'Failed to load plugin: ' . $e->getMessage(), $e);
        }
    }
    
    /**
     * Get plugin class name from plugin name
     * 
     * @param string $pluginName Plugin name
     * @return string Class name
     */
    private function getPluginClassName(string $pluginName): string
    {
        // Convert plugin-name to PluginName
        $parts = explode('-', $pluginName);
        $className = implode('', array_map('ucfirst', $parts));
        
        return 'Plugins\\' . $className . '\\' . $className;
    }
    
    /**
     * Activate a plugin
     * 
     * @param string $pluginName Plugin name
     * @return bool Success status
     */
    public function activatePlugin(string $pluginName): bool
    {
        if (!isset($this->plugins[$pluginName])) {
            $this->logError($pluginName, 'Plugin not found');
            return false;
        }
        
        if (in_array($pluginName, $this->activePlugins)) {
            return true; // Already active
        }
        
        $plugin = $this->plugins[$pluginName];
        
        // Validate dependencies
        if (!$this->validateDependencies($plugin)) {
            $this->logError($pluginName, 'Plugin dependencies not met');
            return false;
        }
        
        try {
            // Register phase
            $plugin->register();
            
            // Boot phase
            $plugin->boot();
            
            $this->activePlugins[] = $pluginName;
            
            // Fire activation hook
            $this->hooks->doAction('plugin.activated', $pluginName, $plugin);
            
            return true;
        } catch (\Throwable $e) {
            $this->logError($pluginName, 'Failed to activate plugin: ' . $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Deactivate a plugin
     * 
     * @param string $pluginName Plugin name
     * @return bool Success status
     */
    public function deactivatePlugin(string $pluginName): bool
    {
        if (!isset($this->plugins[$pluginName])) {
            return false;
        }
        
        if (!in_array($pluginName, $this->activePlugins)) {
            return true; // Already inactive
        }
        
        $plugin = $this->plugins[$pluginName];
        
        try {
            $plugin->deactivate();
            
            $this->activePlugins = array_values(
                array_filter($this->activePlugins, fn($name) => $name !== $pluginName)
            );
            
            // Fire deactivation hook
            $this->hooks->doAction('plugin.deactivated', $pluginName, $plugin);
            
            return true;
        } catch (\Throwable $e) {
            $this->logError($pluginName, 'Failed to deactivate plugin: ' . $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Validate plugin dependencies
     * 
     * @param PluginInterface $plugin Plugin to validate
     * @return bool True if all dependencies are met
     */
    private function validateDependencies(PluginInterface $plugin): bool
    {
        $dependencies = $plugin->getDependencies();
        
        foreach ($dependencies as $dependency) {
            if (!in_array($dependency, $this->activePlugins)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get all active plugins
     * 
     * @return array<string>
     */
    public function getActivePlugins(): array
    {
        return $this->activePlugins;
    }
    
    /**
     * Get all registered plugins
     * 
     * @return array<string, PluginInterface>
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }
    
    /**
     * Get a specific plugin
     * 
     * @param string $pluginName Plugin name
     * @return PluginInterface|null
     */
    public function getPlugin(string $pluginName): ?PluginInterface
    {
        return $this->plugins[$pluginName] ?? null;
    }
    
    /**
     * Check if a plugin is active
     * 
     * @param string $pluginName Plugin name
     * @return bool
     */
    public function isActive(string $pluginName): bool
    {
        return in_array($pluginName, $this->activePlugins);
    }
    
    /**
     * Get plugin errors
     * 
     * @return array<string, array>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Get errors for a specific plugin
     * 
     * @param string $pluginName Plugin name
     * @return array
     */
    public function getPluginErrors(string $pluginName): array
    {
        return $this->errors[$pluginName] ?? [];
    }
    
    /**
     * Log an error for a plugin
     * 
     * @param string $pluginName Plugin name
     * @param string $message Error message
     * @param \Throwable|null $exception Optional exception
     * @return void
     */
    private function logError(string $pluginName, string $message, ?\Throwable $exception = null): void
    {
        if (!isset($this->errors[$pluginName])) {
            $this->errors[$pluginName] = [];
        }
        
        $error = [
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        if ($exception) {
            $error['exception'] = get_class($exception);
            $error['file'] = $exception->getFile();
            $error['line'] = $exception->getLine();
        }
        
        $this->errors[$pluginName][] = $error;
        
        // Fire error hook
        $this->hooks->doAction('plugin.error', $pluginName, $message, $exception);
    }
    
    /**
     * Register a plugin programmatically
     * 
     * Useful for testing or registering plugins without file system
     * 
     * @param string $pluginName Plugin name
     * @param PluginInterface $plugin Plugin instance
     * @return void
     */
    public function registerPlugin(string $pluginName, PluginInterface $plugin): void
    {
        $this->plugins[$pluginName] = $plugin;
    }
    
    /**
     * Register a custom route for a plugin
     * 
     * Allows plugins to register custom API endpoints
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string $path Route path
     * @param callable|array $handler Route handler
     * @return \ChimeraNoWP\Core\Route|null
     */
    public function registerRoute(string $method, string $path, callable|array $handler): ?\ChimeraNoWP\Core\Route
    {
        if ($this->router === null) {
            $this->logError('router', 'Router not available for route registration');
            return null;
        }
        
        $method = strtoupper($method);
        
        return match($method) {
            'GET' => $this->router->get($path, $handler),
            'POST' => $this->router->post($path, $handler),
            'PUT' => $this->router->put($path, $handler),
            'DELETE' => $this->router->delete($path, $handler),
            'PATCH' => $this->router->patch($path, $handler),
            default => null
        };
    }
    
    /**
     * Get the router instance
     * 
     * Allows plugins to access the router for advanced route registration
     * 
     * @return \ChimeraNoWP\Core\Router|null
     */
    public function getRouter(): ?\ChimeraNoWP\Core\Router
    {
        return $this->router;
    }
}
