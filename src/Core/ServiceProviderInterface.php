<?php

namespace Framework\Core;

/**
 * Service Provider Interface
 * 
 * Service providers are responsible for registering services in the container
 * and bootstrapping application components.
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container
     * 
     * @param Container $container
     * @return void
     */
    public function register(Container $container): void;

    /**
     * Bootstrap services after all providers have been registered
     * 
     * @return void
     */
    public function boot(): void;
}
