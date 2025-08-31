<?php

namespace Gwack\Container;

use Gwack\Container\Interfaces\CacheInterface;

/**
 * in-memory cache for container operations
 *
 * Optimized for fast access and minimal memory overhead.
 * Stores reflection data and compiled bindings to avoid repeated
 * expensive operations during service resolution.
 *
 * @package Gwack\Container
 */
class ArrayCache implements CacheInterface
{
    /**
     * @var array Cached reflection data indexed by class name
     */
    private array $reflectionCache = [];

    /**
     * @var array General cache storage
     */
    private array $cache = [];

    /**
     * @var array Cache hit statistics for optimization
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
    ];

    /**
     * Store reflection data for a class
     *
     * @param string $class The class name
     * @param array $data The reflection data to cache
     * @return void
     */
    public function storeReflection(string $class, array $data): void
    {
        $this->reflectionCache[$class] = $data;
    }

    /**
     * Retrieve cached reflection data for a class
     *
     * @param string $class The class name
     * @return array|null The cached reflection data or null if not found
     */
    public function getReflection(string $class): ?array
    {
        if (isset($this->reflectionCache[$class])) {
            $this->stats['hits']++;
            return $this->reflectionCache[$class];
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * Check if reflection data exists for a class
     *
     * @param string $class The class name
     * @return bool
     */
    public function hasReflection(string $class): bool
    {
        return isset($this->reflectionCache[$class]);
    }

    /**
     * Store compiled binding data
     *
     * @param string $key The cache key
     * @param mixed $data The data to cache
     * @return void
     */
    public function store(string $key, mixed $data): void
    {
        $this->cache[$key] = $data;
    }

    /**
     * Retrieve cached data
     *
     * @param string $key The cache key
     * @return mixed The cached data or null if not found
     */
    public function get(string $key): mixed
    {
        if (isset($this->cache[$key])) {
            $this->stats['hits']++;
            return $this->cache[$key];
        }

        $this->stats['misses']++;
        return null;
    }

    /**
     * Check if data exists in cache
     *
     * @param string $key The cache key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    /**
     * Remove an item from cache
     *
     * @param string $key The cache key
     * @return void
     */
    public function forget(string $key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * Clear all cached data
     *
     * @return void
     */
    public function flush(): void
    {
        $this->reflectionCache = [];
        $this->cache = [];
        $this->stats = ['hits' => 0, 'misses' => 0];
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get cache hit ratio
     *
     * @return float
     */
    public function getHitRatio(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        return $total > 0 ? $this->stats['hits'] / $total : 0.0;
    }
}
