<?php

use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Core\ContainerException;

// Test classes for dependency injection
class SimpleClass
{
    public string $value = 'simple';
}

class ClassWithDependency
{
    public function __construct(public SimpleClass $dependency)
    {
    }
}

class ClassWithMultipleDependencies
{
    public function __construct(
        public SimpleClass $first,
        public ClassWithDependency $second
    ) {
    }
}

class ClassWithDefaultValue
{
    public function __construct(public string $name = 'default')
    {
    }
}

class ClassWithNullable
{
    public function __construct(public ?SimpleClass $dependency = null)
    {
    }
}

interface TestInterface
{
}

class TestImplementation implements TestInterface
{
    public string $value = 'implementation';
}

abstract class AbstractClass
{
}

// bind() tests
test('bind() registers a binding with closure', function () {
    $container = new Container();
    $container->bind(SimpleClass::class, fn() => new SimpleClass());
    
    $instance = $container->resolve(SimpleClass::class);
    
    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('bind() registers a binding with class name', function () {
    $container = new Container();
    $container->bind(SimpleClass::class, SimpleClass::class);
    
    $instance = $container->resolve(SimpleClass::class);
    
    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('bind() registers a binding without concrete (self-binding)', function () {
    $container = new Container();
    $container->bind(SimpleClass::class);
    
    $instance = $container->resolve(SimpleClass::class);
    
    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('bind() creates new instance on each resolve (transient)', function () {
    $container = new Container();
    $container->bind(SimpleClass::class);
    
    $instance1 = $container->resolve(SimpleClass::class);
    $instance2 = $container->resolve(SimpleClass::class);
    
    expect($instance1)->not->toBe($instance2);
});

test('bind() binds interface to implementation', function () {
    $container = new Container();
    $container->bind(TestInterface::class, TestImplementation::class);
    
    $instance = $container->resolve(TestInterface::class);
    
    expect($instance)->toBeInstanceOf(TestImplementation::class)
        ->and($instance->value)->toBe('implementation');
});

// singleton() tests
test('singleton() registers a singleton with closure', function () {
    $container = new Container();
    $container->singleton(SimpleClass::class, fn() => new SimpleClass());
    
    $instance = $container->resolve(SimpleClass::class);
    
    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('singleton() registers a singleton with class name', function () {
    $container = new Container();
    $container->singleton(SimpleClass::class, SimpleClass::class);
    
    $instance = $container->resolve(SimpleClass::class);
    
    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('singleton() returns same instance on multiple resolves', function () {
    $container = new Container();
    $container->singleton(SimpleClass::class);
    
    $instance1 = $container->resolve(SimpleClass::class);
    $instance2 = $container->resolve(SimpleClass::class);
    
    expect($instance1)->toBe($instance2);
});

test('singleton() maintains state across resolves', function () {
    $container = new Container();
    $container->singleton(SimpleClass::class);
    
    $instance1 = $container->resolve(SimpleClass::class);
    $instance1->value = 'modified';
    
    $instance2 = $container->resolve(SimpleClass::class);
    
    expect($instance2->value)->toBe('modified');
});

// resolve() tests
test('resolve() resolves class without dependencies', function () {
    $container = new Container();
    $instance = $container->resolve(SimpleClass::class);
    
    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('resolve() resolves class with single dependency (autowiring)', function () {
    $container = new Container();
    $instance = $container->resolve(ClassWithDependency::class);
    
    expect($instance)->toBeInstanceOf(ClassWithDependency::class)
        ->and($instance->dependency)->toBeInstanceOf(SimpleClass::class);
});

test('resolve() resolves class with multiple dependencies (autowiring)', function () {
    $container = new Container();
    $instance = $container->resolve(ClassWithMultipleDependencies::class);
    
    expect($instance)->toBeInstanceOf(ClassWithMultipleDependencies::class)
        ->and($instance->first)->toBeInstanceOf(SimpleClass::class)
        ->and($instance->second)->toBeInstanceOf(ClassWithDependency::class)
        ->and($instance->second->dependency)->toBeInstanceOf(SimpleClass::class);
});

test('resolve() resolves class with default parameter value', function () {
    $container = new Container();
    $instance = $container->resolve(ClassWithDefaultValue::class);
    
    expect($instance)->toBeInstanceOf(ClassWithDefaultValue::class)
        ->and($instance->name)->toBe('default');
});

test('resolve() resolves class with nullable dependency', function () {
    $container = new Container();
    $instance = $container->resolve(ClassWithNullable::class);
    
    // When a nullable dependency can be resolved, it should be resolved
    expect($instance)->toBeInstanceOf(ClassWithNullable::class)
        ->and($instance->dependency)->toBeInstanceOf(SimpleClass::class);
});

test('resolve() throws exception for non-existent class', function () {
    $container = new Container();
    expect(fn() => $container->resolve('NonExistentClass'))
        ->toThrow(ContainerException::class);
});

test('resolve() throws exception for abstract class', function () {
    $container = new Container();
    expect(fn() => $container->resolve(AbstractClass::class))
        ->toThrow(ContainerException::class, 'Cannot instantiate');
});

test('resolve() throws exception for interface without binding', function () {
    $container = new Container();
    expect(fn() => $container->resolve(TestInterface::class))
        ->toThrow(ContainerException::class);
});

// has() tests
test('has() returns true for registered binding', function () {
    $container = new Container();
    $container->bind(SimpleClass::class);
    
    expect($container->has(SimpleClass::class))->toBeTrue();
});

test('has() returns true for registered singleton', function () {
    $container = new Container();
    $container->singleton(SimpleClass::class);
    
    expect($container->has(SimpleClass::class))->toBeTrue();
});

test('has() returns true for resolved singleton instance', function () {
    $container = new Container();
    $container->singleton(SimpleClass::class);
    $container->resolve(SimpleClass::class);
    
    expect($container->has(SimpleClass::class))->toBeTrue();
});

test('has() returns false for unregistered class', function () {
    $container = new Container();
    expect($container->has(SimpleClass::class))->toBeFalse();
});

// get() tests
test('get() is an alias for resolve()', function () {
    $container = new Container();
    $container->bind(SimpleClass::class);
    
    $instance1 = $container->get(SimpleClass::class);
    $instance2 = $container->resolve(SimpleClass::class);
    
    expect($instance1)->toBeInstanceOf(SimpleClass::class)
        ->and($instance2)->toBeInstanceOf(SimpleClass::class);
});

// instance() tests
test('instance() registers an existing instance as singleton', function () {
    $container = new Container();
    $existingInstance = new SimpleClass();
    $existingInstance->value = 'existing';
    
    $container->instance(SimpleClass::class, $existingInstance);
    
    $resolved = $container->resolve(SimpleClass::class);
    
    expect($resolved)->toBe($existingInstance)
        ->and($resolved->value)->toBe('existing');
});

// Complex scenarios
test('resolves nested dependencies with mixed bindings', function () {
    $container = new Container();
    // Bind SimpleClass as singleton
    $container->singleton(SimpleClass::class);
    
    // Resolve ClassWithMultipleDependencies (transient)
    $instance1 = $container->resolve(ClassWithMultipleDependencies::class);
    $instance2 = $container->resolve(ClassWithMultipleDependencies::class);
    
    // Instances should be different (transient)
    expect($instance1)->not->toBe($instance2);
    
    // But their SimpleClass dependencies should be the same (singleton)
    expect($instance1->first)->toBe($instance2->first);
});

test('resolves dependencies through interface binding', function () {
    $container = new Container();
    $container->bind(TestInterface::class, TestImplementation::class);
    
    // Create a class that depends on the interface
    $class = new class($container->resolve(TestInterface::class)) {
        public function __construct(public TestInterface $dependency)
        {
        }
    };
    
    expect($class->dependency)->toBeInstanceOf(TestImplementation::class);
});

test('allows overriding bindings', function () {
    $container = new Container();
    $container->bind(SimpleClass::class, fn() => new SimpleClass());
    
    $first = $container->resolve(SimpleClass::class);
    
    // Override the binding
    $container->bind(SimpleClass::class, function () {
        $instance = new SimpleClass();
        $instance->value = 'overridden';
        return $instance;
    });
    
    $second = $container->resolve(SimpleClass::class);
    
    expect($second->value)->toBe('overridden');
});
