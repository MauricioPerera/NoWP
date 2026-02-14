<?php

/**
 * Translation Manager
 * 
 * Manages translations and internationalization.
 * 
 * Requirements: 14.1, 14.2, 14.3, 14.4, 14.5
 */

declare(strict_types=1);

namespace Framework\Core;

class TranslationManager
{
    private string $translationsPath;
    private string $currentLocale = 'en';
    private string $fallbackLocale = 'en';
    private array $translations = [];
    
    public function __construct(?string $translationsPath = null)
    {
        $this->translationsPath = $translationsPath ?? BASE_PATH . '/translations';
    }
    
    /**
     * Set current locale
     *
     * @param string $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->currentLocale = $locale;
        
        // Load translations for this locale if not already loaded
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }
    }
    
    /**
     * Get current locale
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->currentLocale;
    }
    
    /**
     * Set fallback locale
     *
     * @param string $locale
     * @return void
     */
    public function setFallbackLocale(string $locale): void
    {
        $this->fallbackLocale = $locale;
    }
    
    /**
     * Get fallback locale
     *
     * @return string
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
    }
    
    /**
     * Translate a key
     *
     * @param string $key Translation key (e.g., 'auth.login', 'errors.not_found')
     * @param array $replacements Placeholder replacements
     * @param string|null $locale Specific locale (uses current if null)
     * @return string
     */
    public function translate(string $key, array $replacements = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->currentLocale;
        
        // Load translations if not loaded
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }
        
        // Try to get translation from current locale
        $translation = $this->getTranslationFromArray($key, $this->translations[$locale] ?? []);
        
        // Fallback to fallback locale if not found
        if ($translation === null && $locale !== $this->fallbackLocale) {
            if (!isset($this->translations[$this->fallbackLocale])) {
                $this->loadTranslations($this->fallbackLocale);
            }
            $translation = $this->getTranslationFromArray($key, $this->translations[$this->fallbackLocale] ?? []);
        }
        
        // Return key if no translation found
        if ($translation === null) {
            return $key;
        }
        
        // Replace placeholders
        return $this->replacePlaceholders($translation, $replacements);
    }
    
    /**
     * Get translation from nested array using dot notation
     *
     * @param string $key
     * @param array $translations
     * @return string|null
     */
    private function getTranslationFromArray(string $key, array $translations): ?string
    {
        $keys = explode('.', $key);
        $value = $translations;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }
        
        return is_string($value) ? $value : null;
    }
    
    /**
     * Replace placeholders in translation
     *
     * @param string $translation
     * @param array $replacements
     * @return string
     */
    private function replacePlaceholders(string $translation, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $translation = str_replace(":{$key}", (string)$value, $translation);
        }
        
        return $translation;
    }
    
    /**
     * Load translations for a locale
     *
     * @param string $locale
     * @return void
     */
    private function loadTranslations(string $locale): void
    {
        $this->translations[$locale] = [];
        
        $localePath = $this->translationsPath . '/' . $locale;
        
        if (!is_dir($localePath)) {
            return;
        }
        
        // Load all translation files in the locale directory
        $files = glob($localePath . '/*.php');
        
        foreach ($files as $file) {
            $namespace = basename($file, '.php');
            $translations = require $file;
            
            if (is_array($translations)) {
                $this->translations[$locale][$namespace] = $translations;
            }
        }
        
        // Also support JSON files
        $jsonFiles = glob($localePath . '/*.json');
        
        foreach ($jsonFiles as $file) {
            $namespace = basename($file, '.json');
            $json = file_get_contents($file);
            $translations = json_decode($json, true);
            
            if (is_array($translations)) {
                $this->translations[$locale][$namespace] = $translations;
            }
        }
    }
    
    /**
     * Check if translation exists
     *
     * @param string $key
     * @param string|null $locale
     * @return bool
     */
    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? $this->currentLocale;
        
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }
        
        return $this->getTranslationFromArray($key, $this->translations[$locale] ?? []) !== null;
    }
    
    /**
     * Get all translations for a locale
     *
     * @param string|null $locale
     * @return array
     */
    public function all(?string $locale = null): array
    {
        $locale = $locale ?? $this->currentLocale;
        
        if (!isset($this->translations[$locale])) {
            $this->loadTranslations($locale);
        }
        
        return $this->translations[$locale] ?? [];
    }
    
    /**
     * Detect locale from Accept-Language header
     *
     * @param string $acceptLanguage Accept-Language header value
     * @param array $supportedLocales List of supported locales
     * @return string Best matching locale or fallback
     */
    public function detectLocale(string $acceptLanguage, array $supportedLocales = []): string
    {
        if (empty($supportedLocales)) {
            $supportedLocales = $this->getAvailableLocales();
        }
        
        // Parse Accept-Language header
        $languages = [];
        $parts = explode(',', $acceptLanguage);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            if (preg_match('/^([a-z]{2}(?:-[A-Z]{2})?)(;q=([0-9.]+))?$/', $part, $matches)) {
                $lang = $matches[1];
                $quality = isset($matches[3]) ? (float)$matches[3] : 1.0;
                $languages[$lang] = $quality;
            }
        }
        
        // Sort by quality
        arsort($languages);
        
        // Find best match
        foreach ($languages as $lang => $quality) {
            // Try exact match first
            if (in_array($lang, $supportedLocales)) {
                return $lang;
            }
            
            // Try language without region (e.g., 'en' from 'en-US')
            $langOnly = substr($lang, 0, 2);
            if (in_array($langOnly, $supportedLocales)) {
                return $langOnly;
            }
        }
        
        return $this->fallbackLocale;
    }
    
    /**
     * Get list of available locales
     *
     * @return array
     */
    public function getAvailableLocales(): array
    {
        if (!is_dir($this->translationsPath)) {
            return [$this->fallbackLocale];
        }
        
        $locales = [];
        $dirs = glob($this->translationsPath . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $locales[] = basename($dir);
        }
        
        return $locales;
    }
}
