<?php

namespace Gwack\Container\Interfaces;

/**
 * Interface for caching container bindings and resolved instances
 *
 * Provides caching capabilities for the container
 * to avoid repeated reflection operations and dependency resolution.
 *
 * @package Gwack\Container\Interfaces
 */
interface CacheInterface
{
    /**
     * Store reflection data for a class
     *
     * @param string $class The class name
     * @param array $data The reflection data to cache
     * @return void
     */
    public function storeReflection(string $class, array $data): void;

    /**
     * Retrieve cached reflection data for a class
     *
     * @param string $class The class name
     * @return array|null The cached reflection data or null if not found
     */
    public function getReflection(string $class): ?array;

    /**
     * Check if reflection data exists for a class
     *
     * @param string $class The class name
     * @return bool
     */
    public function hasReflection(string $class): bool;

    /**
     * Store compiled binding data
     *
     * @param string $key The cache key
     * @param mixed $data The data to cache
     * @return void
     */
    public function store(string $key, mixed $data): void;

    /**
     * Retrieve cached data
     *
     * @param string $key The cache key
     * @return mixed The cached data or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Check if data exists in cache
     *
     * @param string $key The cache key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove an item from cache
     *
     * @param string $key The cache key
     * @return void
     */
    public function forget(string $key): void;

    /**
     * Clear all cached data
     *
     * @return void
     */
    public function flush(): void;
}
