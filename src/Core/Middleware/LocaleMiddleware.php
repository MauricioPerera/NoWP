<?php

/**
 * Locale Middleware
 * 
 * Detects and sets the locale for the request.
 * 
 * Requirements: 14.4, 14.5
 */

declare(strict_types=1);

namespace Framework\Core\Middleware;

use Framework\Core\MiddlewareInterface;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Core\TranslationManager;

class LocaleMiddleware implements MiddlewareInterface
{
    private TranslationManager $translator;
    private array $supportedLocales;
    private string $defaultLocale;
    
    public function __construct(
        TranslationManager $translator,
        array $supportedLocales = ['en'],
        string $defaultLocale = 'en'
    ) {
        $this->translator = $translator;
        $this->supportedLocales = $supportedLocales;
        $this->defaultLocale = $defaultLocale;
    }
    
    public function handle(Request $request, callable $next): Response
    {
        $locale = $this->detectLocale($request);
        
        // Set locale in translator
        $this->translator->setLocale($locale);
        
        // Store locale in request for later access
        $request->setAttribute('locale', $locale);
        
        return $next($request);
    }
    
    /**
     * Detect locale from request
     *
     * Priority:
     * 1. Query parameter (?lang=es)
     * 2. Header (X-Locale)
     * 3. Accept-Language header
     * 4. Default locale
     *
     * @param Request $request
     * @return string
     */
    private function detectLocale(Request $request): string
    {
        // 1. Check query parameter
        $queryLocale = $request->query('lang') ?? $request->query('locale');
        if ($queryLocale && $this->isSupported($queryLocale)) {
            return $queryLocale;
        }
        
        // 2. Check X-Locale header
        $headerLocale = $request->getHeader('X-Locale');
        if ($headerLocale && $this->isSupported($headerLocale)) {
            return $headerLocale;
        }
        
        // 3. Check Accept-Language header
        $acceptLanguage = $request->getHeader('Accept-Language');
        if ($acceptLanguage) {
            $detected = $this->translator->detectLocale($acceptLanguage, $this->supportedLocales);
            if ($this->isSupported($detected)) {
                return $detected;
            }
        }
        
        // 4. Return default locale
        return $this->defaultLocale;
    }
    
    /**
     * Check if locale is supported
     *
     * @param string $locale
     * @return bool
     */
    private function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales, true);
    }
}
