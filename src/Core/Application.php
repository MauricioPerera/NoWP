<?php

namespace Framework\Core;

/**
 * Application Class
 * 
 * Core application class that handles bootstrap, lifecycle, and request handling.
 * Manages the dependency injection container and service providers.
 */
class Application
{
    /**
     * The application's dependency injection container
     */
    private Container $container;

    /**
     * Application configuration
     */
    private array $config = [];

    /**
     * Indicates if the application has been bootstrapped
     */
    private bool $booted = false;

    /**
     * Registered service providers
     * @var array<ServiceProviderInterface>
     */
    private array $providers = [];

    /**
     * Create a new application instance
     * 
     * @param array $config Application configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->container = new Container();
        
        // Register the container itself
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Application::class, $this);
    }

    /**
     * Bootstrap the application
     * 
     * Loads configuration, registers service providers, and initializes core services.
     * 
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Load all configuration files
        $this->loadConfiguration();

        // Register core service providers
        $this->registerServiceProviders();

        // Boot all service providers
        $this->bootServiceProviders();

        $this->booted = true;
    }

    /**
     * Handle an incoming request
     * 
     * @param Request $request The incoming HTTP request
     * @return Response The HTTP response
     */
    public function handle(Request $request): Response
    {
        if (!$this->booted) {
            $this->boot();
        }

        try {
            // Store request in container for dependency injection
            $this->container->instance(Request::class, $request);

            // Get router from container
            $router = $this->container->resolve(Router::class);

            // Match route and execute
            $route = $router->match($request);

            if ($route === null) {
                return new Response(
                    json_encode([
                        'error' => [
                            'code' => 'ROUTE_NOT_FOUND',
                            'message' => 'The requested endpoint does not exist'
                        ]
                    ]),
                    404,
                    ['Content-Type' => 'application/json']
                );
            }

            // Execute route handler
            $response = $route->execute($this->container);

            return $response;

        } catch (\Throwable $e) {
            // Handle exceptions through exception handler
            if ($this->container->has(ExceptionHandler::class)) {
                $handler = $this->container->resolve(ExceptionHandler::class);
                return $handler->handle($e);
            }

            // Fallback error response
            return $this->createErrorResponse($e);
        }
    }

    /**
     * Register service providers
     * 
     * Registers all core service providers that provide framework services.
     * 
     * @return void
     */
    public function registerServiceProviders(): void
    {
        // Core service providers will be registered here
        // For now, we'll register basic services directly
        
        // Register Router
        $this->container->singleton(Router::class, function ($container) {
            return new Router();
        });

        // Register Exception Handler
        $this->container->singleton(ExceptionHandler::class, function ($container) {
            $debug = $this->config('app.debug', false);
            $logPath = $this->config('app.log.path', BASE_PATH . '/storage/logs') . '/error.log';
            
            return new ExceptionHandler($debug, $logPath);
        });

        // Additional service providers can be added here
        // Example: $this->registerProvider(new DatabaseServiceProvider());
    }

    /**
     * Load configuration from config files
     * 
     * @return void
     */
    private function loadConfiguration(): void
    {
        $configPath = BASE_PATH . '/config';
        
        if (!is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath . '/*.php') as $file) {
            $name = basename($file, '.php');
            // Only load if not already set in constructor
            if (!isset($this->config[$name])) {
                $this->config[$name] = require $file;
            } else {
                // Merge with existing config
                $this->config[$name] = array_merge(
                    require $file,
                    $this->config[$name]
                );
            }
        }
    }

    /**
     * Boot all registered service providers
     * 
     * @return void
     */
    private function bootServiceProviders(): void
    {
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot();
            }
        }
    }

    /**
     * Register a service provider
     * 
     * @param ServiceProviderInterface $provider
     * @return void
     */
    public function registerProvider(ServiceProviderInterface $provider): void
    {
        $provider->register($this->container);
        $this->providers[] = $provider;
        
        // If already booted, boot the provider immediately
        if ($this->booted && method_exists($provider, 'boot')) {
            $provider->boot();
        }
    }

    /**
     * Get the application's container
     * 
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get configuration value
     * 
     * @param string $key Configuration key in dot notation
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }

    /**
     * Check if application has been booted
     * 
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Create a fallback error response
     * 
     * @param \Throwable $e
     * @return Response
     */
    private function createErrorResponse(\Throwable $e): Response
    {
        $debug = $this->config('app.debug', false);
        
        $error = [
            'error' => [
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => 'An unexpected error occurred'
            ]
        ];

        if ($debug) {
            $error['error']['debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        return new Response(
            json_encode($error),
            500,
            ['Content-Type' => 'application/json']
        );
    }
}
