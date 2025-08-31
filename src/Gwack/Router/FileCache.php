<?php

namespace Gwack\Router;

use Gwack\Router\Interfaces\CacheInterface;

/**
 * Class FileCache
 * 
 * A file-based cache implementation for route compilation
 * 
 * @package Gwack\Router
 */
class FileCache implements CacheInterface
{
    /**
     * @var string Cache directory path
     */
    private string $cacheDir;

    /**
     * @var string Cache prefix
     */
    private string $prefix;

    /**
     * FileCache constructor
     * 
     * @param string $cacheDir Directory to store cache files
     * @param string $prefix Optional prefix for cache keys
     */
    public function __construct(string $cacheDir = null, string $prefix = 'routerv1_')
    {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir();
        $this->prefix = $prefix;

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $path = $this->getFilePath($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = unserialize($content);

        // Check if expired
        if ($data['expiry'] > 0 && $data['expiry'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $path = $this->getFilePath($key);
        $expiry = $ttl > 0 ? time() + $ttl : 0;

        $data = [
            'value' => $value,
            'expiry' => $expiry,
            'created' => time()
        ];

        $result = file_put_contents($path, serialize($data), LOCK_EX);

        return $result !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (!file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return false;
        }

        $data = unserialize($content);

        // Check if expired
        if ($data['expiry'] > 0 && $data['expiry'] < time()) {
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
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    /**
     * Generate a file path for a cache key
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private function getFilePath(string $key): string
    {
        $filename = $this->prefix . md5($key) . '.cache';
        return $this->cacheDir . DIRECTORY_SEPARATOR . $filename;
    }
}
