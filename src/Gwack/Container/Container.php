<?php

namespace Gwack\Container;

use Gwack\Container\Interfaces\ContainerInterface;
use Gwack\Container\Interfaces\ResolverInterface;
use Gwack\Container\Interfaces\CacheInterface;
use Gwack\Container\Exceptions\NotFoundException;
use Gwack\Container\Exceptions\ContainerException;

/**
 * Dependency injection container
 *
 * @package Gwack\Container
 */
class Container implements ContainerInterface
{
    /**
     * @var array Service bindings storage
     */
    private array $bindings = [];

    /**
     * @var array Singleton instances storage
     */
    private array $instances = [];

    /**
     * @var array Contextual bindings for complex dependency scenarios
     */
    private array $contextualBindings = [];

    /**
     * @var ResolverInterface Service resolver
     */
    private ResolverInterface $resolver;

    /**
     * @var CacheInterface Cache implementation
     */
    private CacheInterface $cache;

    /**
     * @var array Aliases for service identifiers
     */
    private array $aliases = [];

    /**
     * @var array Tagged services for group resolution
     */
    private array $tags = [];

    /**
     * @var array Method call cache for performance
     */
    private array $methodCache = [];

    /**
     * @var array Function bindings storage
     */
    private array $functions = [];

    /**
     * Constructor
     *
     * @param CacheInterface|null $cache Optional cache implementation
     */
    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? new ArrayCache();
        $this->resolver = new Resolver($this, $this->cache);

        // Bind the container itself
        $this->instance(ContainerInterface::class, $this);
        $this->instance(Container::class, $this);
        $this->instance('container', $this);
    }

    /**
     * Bind a concrete implementation to an abstract identifier
     *
     * @param string $abstract The service identifier
     * @param mixed $concrete The concrete implementation (class name, callable, or instance)
     * @param bool $singleton Whether to treat as singleton
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null, bool $singleton = false): void
    {
        // Remove any existing instance if we're rebinding
        if (isset($this->instances[$abstract])) {
            unset($this->instances[$abstract]);
        }

        // If no concrete is provided, use the abstract as concrete
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
            'shared' => false,
        ];
    }

    /**
     * Bind a singleton service
     *
     * @param string $abstract The service identifier
     * @param mixed $concrete The concrete implementation
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind an existing instance as singleton
     *
     * @param string $abstract The service identifier
     * @param object $instance The instance to bind
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
        $this->bindings[$abstract] = [
            'concrete' => $instance,
            'singleton' => true,
            'shared' => true,
        ];
    }

    /**
     * Get a service from the container
     *
     * @param string $id The service identifier
     * @return mixed
     * @throws NotFoundException
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (ContainerException $e) {
            if (!$this->has($id)) {
                throw new NotFoundException($id, 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Check if a service exists in the container
     *
     * @param string $id The service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) ||
            isset($this->instances[$id]) ||
            isset($this->aliases[$id]) ||
            class_exists($id);
    }

    /**
     * Create a new instance bypassing any existing bindings
     *
     * @param string $abstract The service identifier
     * @param array $parameters Optional parameters for construction
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->resolver->resolve($abstract, $parameters);
    }

    /**
     * Resolve a service from the container
     *
     * @param string $abstract The service identifier
     * @param array $parameters Optional parameters
     * @return mixed
     */
    private function resolve(string $abstract, array $parameters = []): mixed
    {
        // Check for alias
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        // Return existing instance if it's a singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Check for contextual binding
        $contextual = $this->getContextualConcrete($abstract);
        if ($contextual !== null) {
            return $this->resolver->resolve($contextual, $parameters);
        }

        // Resolve normally
        $concrete = $this->resolver->resolve($abstract, $parameters);

        // Store as singleton if needed
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['singleton']) {
            $this->instances[$abstract] = $concrete;
        }

        return $concrete;
    }

    /**
     * Check if a service is bound in the container
     *
     * @param string $abstract The service identifier
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Remove a binding from the container
     *
     * @param string $abstract The service identifier
     * @return void
     */
    public function unbind(string $abstract): void
    {
        unset($this->bindings[$abstract], $this->instances[$abstract]);
    }

    /**
     * Get all bindings
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Clear all bindings and instances
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->contextualBindings = [];
        $this->functions = [];
        $this->aliases = [];
        $this->tags = [];
        $this->methodCache = [];
        $this->cache->flush();

        // Re-bind the container itself
        $this->instance(ContainerInterface::class, $this);
        $this->instance(Container::class, $this);
        $this->instance('container', $this);
    }

    /**
     * Add a contextual binding
     *
     * @param string $concrete The concrete class that needs the dependency
     * @param string $abstract The abstract dependency
     * @param mixed $implementation The implementation to inject
     * @return void
     */
    public function when(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextualBindings[$concrete][$abstract] = $implementation;
    }

    /**
     * Call a method with automatic dependency injection
     *
     * @param callable|array $callback The callback to call
     * @param array $parameters Optional parameters
     * @return mixed
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;

            // Resolve the class if it's a string
            if (is_string($class)) {
                $class = $this->get($class);
            }

            $cacheKey = get_class($class) . '::' . $method;

            // Check method cache
            if (!isset($this->methodCache[$cacheKey])) {
                $this->methodCache[$cacheKey] = new \ReflectionMethod($class, $method);
            }

            $reflection = $this->methodCache[$cacheKey];
            $dependencies = $this->resolver->resolveMethodDependencies($reflection, $parameters);

            return $class->$method(...$dependencies);
        }

        if ($callback instanceof \Closure) {
            $reflection = new \ReflectionFunction($callback);
            $dependencies = $this->resolver->resolveMethodDependencies($reflection, $parameters);
            return $callback(...$dependencies);
        }

        return $callback($this, $parameters);
    }

    /**
     * Set an alias for a service
     *
     * @param string $alias The alias name
     * @param string $abstract The service identifier
     * @return void
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Tag services for group resolution
     *
     * @param array|string $abstracts The service identifiers
     * @param string $tag The tag name
     * @return void
     */
    public function tag(array|string $abstracts, string $tag): void
    {
        $abstracts = is_array($abstracts) ? $abstracts : [$abstracts];

        foreach ($abstracts as $abstract) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * Resolve all services with a specific tag
     *
     * @param string $tag The tag name
     * @return array
     */
    public function tagged(string $tag): array
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        $services = [];
        foreach ($this->tags[$tag] as $abstract) {
            $services[] = $this->get($abstract);
        }

        return $services;
    }

    /**
     * Get contextual concrete implementation
     *
     * @param string $abstract The abstract service
     * @return string|null
     */
    private function getContextualConcrete(string $abstract): ?string
    {
        // This is a simplified implementation
        // In a real-world scenario, you'd need to track the resolution context
        return null;
    }

    /**
     * Get container statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'bindings' => count($this->bindings),
            'instances' => count($this->instances),
            'aliases' => count($this->aliases),
            'tags' => count($this->tags),
            'contextual_bindings' => count($this->contextualBindings),
            'cache_stats' => method_exists($this->cache, 'getStats') ? $this->cache->getStats() : null,
        ];
    }

    /**
     * Bind a function to the container
     *
     * @param string $name The function name
     * @param callable $function The function to bind
     * @return void
     */
    public function bindFunction(string $name, callable $function): void
    {
        $this->functions[$name] = $function;
    }

    /**
     * Get a function from the container
     *
     * @param string $name The function name
     * @return callable|null
     */
    public function getFunction(string $name): ?callable
    {
        return $this->functions[$name] ?? null;
    }

    /**
     * Check if a function is bound
     *
     * @param string $name The function name
     * @return bool
     */
    public function hasFunction(string $name): bool
    {
        return isset($this->functions[$name]);
    }

    /**
     * Get all bound functions
     *
     * @return array<string, callable>
     */
    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * Remove a function binding
     *
     * @param string $name The function name
     * @return void
     */
    public function unbindFunction(string $name): void
    {
        unset($this->functions[$name]);
    }
}
