<?php

namespace Gwack\Container\Interfaces;

/**
 * Interface for service resolver implementations
 *
 * Handles the actual resolution of services from bindings,
 * including dependency injection and parameter resolution.
 *
 * @package Gwack\Container\Interfaces
 */
interface ResolverInterface
{
    /**
     * Resolve a service from its binding
     *
     * @param string $abstract The service identifier
     * @param array $parameters Optional parameters for construction
     * @return mixed
     */
    public function resolve(string $abstract, array $parameters = []): mixed;

    /**
     * Build a concrete class with dependency injection
     *
     * @param string $concrete The concrete class name
     * @param array $parameters Optional parameters
     * @return object
     */
    public function build(string $concrete, array $parameters = []): object;

    /**
     * Resolve method dependencies for dependency injection
     *
     * @param \ReflectionMethod|\ReflectionFunction $method The method to analyze
     * @param array $parameters Optional parameters
     * @return array
     */
    public function resolveMethodDependencies(\ReflectionMethod|\ReflectionFunction $method, array $parameters = []): array;

    /**
     * Resolve constructor dependencies
     *
     * @param string $concrete The concrete class name
     * @param array $parameters Optional parameters
     * @return array
     */
    public function resolveConstructorDependencies(string $concrete, array $parameters = []): array;
}
