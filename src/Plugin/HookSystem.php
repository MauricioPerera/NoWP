<?php

/**
 * Hook System
 * 
 * Provides WordPress-style hooks (actions and filters) for extensibility.
 * Allows plugins to modify behavior without changing core code.
 * 
 * Requirements: 4.3
 */

declare(strict_types=1);

namespace ChimeraNoWP\Plugin;

class HookSystem
{
    private array $actions = [];
    private array $filters = [];
    private array $actionsSorted = [];
    private array $filtersSorted = [];
    
    /**
     * Add an action hook
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority (lower = earlier execution)
     * @return void
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset($this->actions[$hook])) {
            $this->actions[$hook] = [];
        }
        
        if (!isset($this->actions[$hook][$priority])) {
            $this->actions[$hook][$priority] = [];
        }
        
        $this->actions[$hook][$priority][] = $callback;
        unset($this->actionsSorted[$hook]);
    }

    /**
     * Execute action hooks
     *
     * @param string $hook Hook name
     * @param mixed ...$args Arguments to pass to callbacks
     * @return void
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        if (!isset($this->actions[$hook])) {
            return;
        }
        
        // Sort by priority (only if not already sorted)
        if (!isset($this->actionsSorted[$hook])) {
            ksort($this->actions[$hook]);
            $this->actionsSorted[$hook] = true;
        }

        foreach ($this->actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }
    
    /**
     * Add a filter hook
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority (lower = earlier execution)
     * @return void
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset($this->filters[$hook])) {
            $this->filters[$hook] = [];
        }
        
        if (!isset($this->filters[$hook][$priority])) {
            $this->filters[$hook][$priority] = [];
        }
        
        $this->filters[$hook][$priority][] = $callback;
        unset($this->filtersSorted[$hook]);
    }

    /**
     * Apply filter hooks
     *
     * @param string $hook Hook name
     * @param mixed $value Value to filter
     * @param mixed ...$args Additional arguments to pass to callbacks
     * @return mixed Filtered value
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->filters[$hook])) {
            return $value;
        }
        
        // Sort by priority (only if not already sorted)
        if (!isset($this->filtersSorted[$hook])) {
            ksort($this->filters[$hook]);
            $this->filtersSorted[$hook] = true;
        }

        foreach ($this->filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = call_user_func_array($callback, array_merge([$value], $args));
            }
        }
        
        return $value;
    }
}
