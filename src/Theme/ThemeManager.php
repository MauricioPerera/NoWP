<?php

/**
 * Theme Manager
 * 
 * Manages theme loading, template rendering, and parent/child theme inheritance.
 * 
 * Requirements: 8.1, 8.2, 8.3
 */

declare(strict_types=1);

namespace Framework\Theme;

class ThemeManager
{
    private string $themesPath;
    private ?string $activeTheme = null;
    private ?string $parentTheme = null;
    private array $themeConfig = [];
    
    public function __construct(string $themesPath)
    {
        $this->themesPath = rtrim($themesPath, '/');
    }
    
    /**
     * Load a theme and its configuration
     *
     * @param string $themeName Theme directory name
     * @return bool Success status
     */
    public function loadTheme(string $themeName): bool
    {
        $themePath = $this->themesPath . '/' . $themeName;
        
        if (!is_dir($themePath)) {
            return false;
        }
        
        $this->activeTheme = $themeName;
        
        // Load theme configuration if exists
        $configFile = $themePath . '/theme.php';
        if (file_exists($configFile)) {
            $this->themeConfig = require $configFile;
            
            // Check for parent theme
            if (isset($this->themeConfig['parent'])) {
                $this->parentTheme = $this->themeConfig['parent'];
            }
        }
        
        return true;
    }
    
    /**
     * Render a template with data
     *
     * @param string $template Template name (without .php extension)
     * @param array $data Data to pass to template
     * @return string Rendered template content
     * @throws \RuntimeException If template not found
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);
        
        if (!$templatePath) {
            throw new \RuntimeException("Template '{$template}' not found");
        }
        
        // Extract data to variables
        extract($data, EXTR_SKIP);
        
        // Start output buffering
        ob_start();
        
        try {
            require $templatePath;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * Find template file with parent/child theme support
     *
     * @param string $template Template name
     * @return string|null Template file path or null if not found
     */
    private function findTemplate(string $template): ?string
    {
        // Ensure .php extension
        if (!str_ends_with($template, '.php')) {
            $template .= '.php';
        }
        
        // Check child theme first
        if ($this->activeTheme) {
            $childPath = $this->themesPath . '/' . $this->activeTheme . '/' . $template;
            if (file_exists($childPath)) {
                return $childPath;
            }
        }
        
        // Check parent theme
        if ($this->parentTheme) {
            $parentPath = $this->themesPath . '/' . $this->parentTheme . '/' . $template;
            if (file_exists($parentPath)) {
                return $parentPath;
            }
        }
        
        // Check default theme
        $defaultPath = $this->themesPath . '/default/' . $template;
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }
        
        return null;
    }
    
    /**
     * Get active theme name
     *
     * @return string|null
     */
    public function getActiveTheme(): ?string
    {
        return $this->activeTheme;
    }
    
    /**
     * Get parent theme name
     *
     * @return string|null
     */
    public function getParentTheme(): ?string
    {
        return $this->parentTheme;
    }
    
    /**
     * Get theme configuration
     *
     * @param string|null $key Configuration key (null for all)
     * @return mixed
     */
    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->themeConfig;
        }
        
        return $this->themeConfig[$key] ?? null;
    }
    
    /**
     * Check if template exists
     *
     * @param string $template Template name
     * @return bool
     */
    public function templateExists(string $template): bool
    {
        return $this->findTemplate($template) !== null;
    }
    
    /**
     * Get template path
     *
     * @param string $template Template name
     * @return string|null
     */
    public function getTemplatePath(string $template): ?string
    {
        return $this->findTemplate($template);
    }
}
