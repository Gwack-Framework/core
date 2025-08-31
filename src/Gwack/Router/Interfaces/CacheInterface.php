<?php

namespace Gwack\Router\Interfaces;

/**
 * Interface CacheInterface
 * 
 * Defines the contract for route compilation caching
 * 
 * @package Gwack\RouterInterfaces
 */
interface CacheInterface
{
    /**
     * Get an item from the cache
     * 
     * @param string $key Cache key
     * @return mixed|null The cached value or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Store an item in the cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = forever)
     * @return bool Success or failure
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Check if an item exists in the cache
     * 
     * @param string $key Cache key
     * @return bool True if the item exists
     */
    public function has(string $key): bool;

    /**
     * Remove an item from the cache
     * 
     * @param string $key Cache key
     * @return bool Success or failure
     */
    public function delete(string $key): bool;
}
