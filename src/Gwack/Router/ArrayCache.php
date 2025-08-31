<?php

namespace Gwack\Router;

use Gwack\Router\Interfaces\CacheInterface;

/**
 * Class ArrayCache
 * 
 * A simple in-memory cache implementation
 * 
 * @package Gwack\Router
 */
class ArrayCache implements CacheInterface
{
    /**
     * @var array Cache data [key => [value, expiry]]
     */
    private array $cache = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->cache[$key]['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $expiry = $ttl > 0 ? time() + $ttl : 0;

        $this->cache[$key] = [
            'value' => $value,
            'expiry' => $expiry
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $item = $this->cache[$key];

        // Check if expired
        if ($item['expiry'] > 0 && $item['expiry'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }
}
