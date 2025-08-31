<?php

namespace Gwack\Container\Interfaces;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Enhanced container interface with performance optimizations
 *
 * Extends PSR-11 ContainerInterface with additional features for
 * dependency injection and service management.
 *
 * @package Gwack\Container\Interfaces
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Bind a concrete implementation to an abstract identifier
     *
     * @param string $abstract The service identifier
     * @param mixed $concrete The concrete implementation (class name, callable, or instance)
     * @param bool $singleton Whether to treat as singleton
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null, bool $singleton = false): void;

    /**
     * Bind a singleton service
     *
     * @param string $abstract The service identifier
     * @param mixed $concrete The concrete implementation
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Bind an existing instance as singleton
     *
     * @param string $abstract The service identifier
     * @param object $instance The instance to bind
     * @return void
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Create a new instance bypassing any existing bindings
     *
     * @param string $abstract The service identifier
     * @param array $parameters Optional parameters for construction
     * @return mixed
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Check if a service is bound in the container
     *
     * @param string $abstract The service identifier
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * Remove a binding from the container
     *
     * @param string $abstract The service identifier
     * @return void
     */
    public function unbind(string $abstract): void;

    /**
     * Get all bindings
     *
     * @return array
     */
    public function getBindings(): array;

    /**
     * Clear all bindings and instances
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Add a contextual binding
     *
     * @param string $concrete The concrete class that needs the dependency
     * @param string $abstract The abstract dependency
     * @param mixed $implementation The implementation to inject
     * @return void
     */
    public function when(string $concrete, string $abstract, mixed $implementation): void;

    /**
     * Call a method with automatic dependency injection
     *
     * @param callable|array $callback The callback to call
     * @param array $parameters Optional parameters
     * @return mixed
     */
    public function call(callable|array $callback, array $parameters = []): mixed;
}
