<?php

namespace Framework\Core;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Dependency Injection Container
 * 
 * Provides service registration and resolution with autowiring support.
 * Supports both transient and singleton bindings.
 */
class Container
{
    /**
     * Registered bindings (transient instances)
     * @var array<string, Closure>
     */
    private array $bindings = [];

    /**
     * Registered singletons (shared instances)
     * @var array<string, Closure>
     */
    private array $singletons = [];

    /**
     * Resolved singleton instances
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Register a binding in the container (transient)
     * 
     * @param string $abstract The interface or class name
     * @param Closure|string|null $concrete The implementation or factory
     * @return void
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (is_string($concrete)) {
            $className = $concrete;
            $concrete = fn($container) => $container->build($className);
        }

        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Register a singleton binding in the container
     * 
     * @param string $abstract The interface or class name
     * @param Closure|string|null $concrete The implementation or factory
     * @return void
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if (is_string($concrete)) {
            $className = $concrete;
            $concrete = fn($container) => $container->build($className);
        }

        $this->singletons[$abstract] = $concrete;
    }

    /**
     * Resolve a dependency from the container
     * 
     * @param string $abstract The interface or class name to resolve
     * @return mixed The resolved instance
     * @throws ContainerException If the dependency cannot be resolved
     */
    public function resolve(string $abstract): mixed
    {
        // Check if we have a singleton instance already
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check if we have a singleton binding
        if (isset($this->singletons[$abstract])) {
            $instance = $this->singletons[$abstract]($this);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        // Check if we have a transient binding
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        // Try to autowire the class
        return $this->build($abstract);
    }

    /**
     * Build a concrete instance using reflection and autowiring
     * 
     * @param string $concrete The class name to build
     * @return object The built instance
     * @throws ContainerException If the class cannot be instantiated
     */
    private function build(string $concrete): object
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Cannot resolve class [{$concrete}]: {$e->getMessage()}",
                0,
                $e
            );
        }

        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new ContainerException(
                "Cannot instantiate [{$concrete}]. Class may be abstract or an interface."
            );
        }

        $constructor = $reflector->getConstructor();

        // No constructor, just instantiate
        if ($constructor === null) {
            return new $concrete();
        }

        // Get constructor parameters
        $parameters = $constructor->getParameters();

        // Resolve dependencies
        $dependencies = $this->resolveDependencies($parameters);

        // Create instance with dependencies
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     * 
     * @param array<ReflectionParameter> $parameters
     * @return array<mixed> Resolved dependencies
     * @throws ContainerException If a dependency cannot be resolved
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveParameter($parameter);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single parameter dependency
     * 
     * @param ReflectionParameter $parameter
     * @return mixed The resolved dependency
     * @throws ContainerException If the parameter cannot be resolved
     */
    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        // No type hint
        if ($type === null) {
            // Check if parameter has default value
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(
                "Cannot resolve parameter [{$parameter->getName()}] without type hint or default value"
            );
        }

        // Handle union types (PHP 8.0+)
        if (!$type instanceof ReflectionNamedType) {
            throw new ContainerException(
                "Cannot resolve parameter [{$parameter->getName()}] with union or intersection types"
            );
        }

        // Get the type name
        $typeName = $type->getName();

        // Handle built-in types
        if ($type->isBuiltin()) {
            // Check if parameter has default value
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(
                "Cannot resolve built-in type [{$typeName}] for parameter [{$parameter->getName()}]"
            );
        }

        // Resolve class dependency
        try {
            return $this->resolve($typeName);
        } catch (ContainerException $e) {
            // If nullable and resolution fails, return null
            if ($type->allowsNull()) {
                return null;
            }

            throw new ContainerException(
                "Cannot resolve dependency [{$typeName}] for parameter [{$parameter->getName()}]: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Check if a binding exists
     * 
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) 
            || isset($this->singletons[$abstract])
            || isset($this->instances[$abstract]);
    }

    /**
     * Get an instance from the container (alias for resolve)
     * 
     * @param string $abstract
     * @return mixed
     */
    public function get(string $abstract): mixed
    {
        return $this->resolve($abstract);
    }

    /**
     * Register an existing instance as a singleton
     * 
     * @param string $abstract
     * @param object $instance
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }
}
