<?php

namespace Gwack\Container;

use Gwack\Container\Interfaces\ResolverInterface;
use Gwack\Container\Interfaces\CacheInterface;
use Gwack\Container\Interfaces\ContainerInterface;
use Gwack\Container\Exceptions\BindingResolutionException;
use Gwack\Container\Exceptions\CircularDependencyException;

/**
 * Service resolver with dependency injection
 *
 * @package Gwack\Container
 */
class Resolver implements ResolverInterface
{
    /**
     * @var ContainerInterface The container instance
     */
    private ContainerInterface $container;

    /**
     * @var CacheInterface Cache for reflection data
     */
    private CacheInterface $cache;

    /**
     * @var array Resolution stack for circular dependency detection
     */
    private array $resolutionStack = [];

    /**
     * @var array Cached reflection instances to avoid repeated reflection
     */
    private array $reflectionCache = [];

    /**
     * Constructor
     *
     * @param ContainerInterface $container The container instance
     * @param CacheInterface $cache The cache implementation
     */
    public function __construct(ContainerInterface $container, CacheInterface $cache)
    {
        $this->container = $container;
        $this->cache = $cache;
    }

    /**
     * Resolve a service from its binding
     *
     * @param string $abstract The service identifier
     * @param array $parameters Optional parameters for construction
     * @return mixed
     * @throws CircularDependencyException
     * @throws BindingResolutionException
     */
    public function resolve(string $abstract, array $parameters = []): mixed
    {
        // Check for circular dependencies
        if (in_array($abstract, $this->resolutionStack, true)) {
            throw new CircularDependencyException($abstract, $this->resolutionStack);
        }

        $this->resolutionStack[] = $abstract;

        try {
            $resolved = $this->doResolve($abstract, $parameters);
            array_pop($this->resolutionStack);
            return $resolved;
        } catch (\Throwable $e) {
            array_pop($this->resolutionStack);
            throw $e;
        }
    }

    /**
     * Perform the actual resolution
     *
     * @param string $abstract The service identifier
     * @param array $parameters Optional parameters
     * @return mixed
     * @throws BindingResolutionException
     */
    private function doResolve(string $abstract, array $parameters = []): mixed
    {
        $bindings = $this->container->getBindings();

        if (!isset($bindings[$abstract])) {
            // Try to auto-resolve if it's a concrete class
            if (class_exists($abstract)) {
                return $this->build($abstract, $parameters);
            }
            throw new BindingResolutionException($abstract, 'No binding found and not a concrete class');
        }

        $binding = $bindings[$abstract];
        $concrete = $binding['concrete'];

        // Handle different types of bindings
        if (is_string($concrete)) {
            if (class_exists($concrete)) {
                return $this->build($concrete, $parameters);
            }
            // It might be another abstract that needs resolution
            return $this->resolve($concrete, $parameters);
        }

        if (is_callable($concrete)) {
            return $this->resolveCallable($concrete, $parameters);
        }

        if (is_object($concrete)) {
            return $concrete;
        }

        throw new BindingResolutionException($abstract, 'Invalid binding type');
    }

    /**
     * Resolve a callable binding
     *
     * @param callable $callable The callable to resolve
     * @param array $parameters Optional parameters
     * @return mixed
     */
    private function resolveCallable(callable $callable, array $parameters = []): mixed
    {
        // If it's a closure, we can inject dependencies
        if ($callable instanceof \Closure) {
            $reflection = new \ReflectionFunction($callable);
            $dependencies = $this->resolveMethodDependencies($reflection, $parameters);
            return $callable(...$dependencies);
        }

        // For other callables, just call them with the container and parameters
        if (is_array($callable) && count($callable) === 2) {
            [$class, $method] = $callable;
            if (is_string($class)) {
                $class = $this->container->get($class);
            }
            $reflection = new \ReflectionMethod($class, $method);
            $dependencies = $this->resolveMethodDependencies($reflection, $parameters);
            return $class->$method(...$dependencies);
        }

        // For regular callables, pass the container as first parameter
        return $callable($this->container, ...$parameters);
    }

    /**
     * Build a concrete class with dependency injection
     *
     * @param string $concrete The concrete class name
     * @param array $parameters Optional parameters
     * @return object
     * @throws BindingResolutionException
     */
    public function build(string $concrete, array $parameters = []): object
    {
        if (!class_exists($concrete)) {
            throw new BindingResolutionException($concrete, 'Class does not exist');
        }

        $reflection = $this->getReflectionClass($concrete);

        if (!$reflection->isInstantiable()) {
            throw new BindingResolutionException($concrete, 'Class is not instantiable');
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveMethodDependencies($constructor, $parameters);

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve method dependencies for dependency injection
     *
     * @param \ReflectionMethod|\ReflectionFunction $method The method to analyze
     * @param array $parameters Optional parameters
     * @return array
     * @throws BindingResolutionException
     */
    public function resolveMethodDependencies(\ReflectionMethod|\ReflectionFunction $method, array $parameters = []): array
    {
        $dependencies = [];
        $methodParameters = $method->getParameters();

        foreach ($methodParameters as $parameter) {
            $name = $parameter->getName();

            // Check if parameter is provided explicitly
            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            // Check if parameter is provided by index
            $index = $parameter->getPosition();
            if (array_key_exists($index, $parameters)) {
                $dependencies[] = $parameters[$index];
                continue;
            }

            // Special handling for container parameter
            if ($name === 'container' || $name === 'c') {
                $dependencies[] = $this->container;
                continue;
            }

            // Try to resolve by type
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

                // Special handling for container types
                if (
                    $typeName === 'Psr\Container\ContainerInterface' ||
                    $typeName === 'Gwack\Container\Interfaces\ContainerInterface' ||
                    is_subclass_of($typeName, 'Psr\Container\ContainerInterface')
                ) {
                    $dependencies[] = $this->container;
                    continue;
                }

                if ($this->container->has($typeName)) {
                    $dependencies[] = $this->container->get($typeName);
                    continue;
                }
                if (class_exists($typeName)) {
                    $dependencies[] = $this->resolve($typeName);
                    continue;
                }
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // Parameter is nullable
            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new BindingResolutionException(
                $parameter->getDeclaringClass()?->getName() ?? 'Unknown',
                "Cannot resolve parameter '{$name}'"
            );
        }

        return $dependencies;
    }

    /**
     * Resolve constructor dependencies
     *
     * @param string $concrete The concrete class name
     * @param array $parameters Optional parameters
     * @return array
     */
    public function resolveConstructorDependencies(string $concrete, array $parameters = []): array
    {
        $reflection = $this->getReflectionClass($concrete);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        return $this->resolveMethodDependencies($constructor, $parameters);
    }

    /**
     * Get reflection class with caching
     *
     * @param string $class The class name
     * @return \ReflectionClass
     */
    private function getReflectionClass(string $class): \ReflectionClass
    {
        if (!isset($this->reflectionCache[$class])) {
            $this->reflectionCache[$class] = new \ReflectionClass($class);
        }

        return $this->reflectionCache[$class];
    }
}
