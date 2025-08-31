<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Gwack\Router\Router;
use Gwack\Router\Route;
use Gwack\Router\RouteCollection;
use Gwack\Router\ArrayCache;

class RouterCacheTest extends TestCase
{
    private Router $router;
    private ArrayCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new ArrayCache();
        $collection = new RouteCollection();
        $this->router = new Router($collection, $this->cache);
    }

    /**
     * Test that routes are properly cached
     */
    public function testRoutesCaching(): void
    {
        // Add some routes
        $this->router->get('/users', fn() => 'users list');
        $this->router->get('/users/{id}', fn($id) => "user $id");
        $this->router->post('/users', fn() => 'create user');

        // First match will compile and cache
        $result = $this->router->match('GET', '/users/123');
        $this->assertNotNull($result);

        // Verify cache has data
        $cacheKey = new \ReflectionProperty($this->router, 'cacheKey');
        $cacheKey->setAccessible(true);
        $key = $cacheKey->getValue($this->router);

        $this->assertTrue($this->cache->has($key));

        // Create a new router with the same cache
        $newRouter = new Router(new RouteCollection(), $this->cache);

        // Add the same routes
        $newRouter->get('/users', fn() => 'users list');
        $newRouter->get('/users/{id}', fn($id) => "user $id");
        $newRouter->post('/users', fn() => 'create user');

        // The routes should be loaded from cache without recompilation
        $routesCompiled = new \ReflectionProperty($newRouter, 'routesCompiled');
        $routesCompiled->setAccessible(true);

        // Match should work with cached routes
        $result = $newRouter->match('GET', '/users/123');
        $this->assertNotNull($result);
    }

    /**
     * Test named routes with caching
     */
    public function testNamedRoutesWithCaching(): void
    {
        $this->router->addNamedRoute('GET', '/profile/{id}', fn($id) => "Profile $id", 'profile');
        $this->router->where('id', '\d+');

        // Match to trigger compilation and caching
        $result = $this->router->match('GET', '/profile/123');
        $this->assertNotNull($result);

        // Create a new router with the same cache
        $newRouter = new Router(new RouteCollection(), $this->cache);
        $newRouter->addNamedRoute('GET', '/profile/{id}', fn($id) => "Profile $id", 'profile');
        $newRouter->where('id', '\d+');

        // Should work with cached routes
        $result = $newRouter->match('GET', '/profile/123');
        $this->assertNotNull($result);

        // Should respect regex constraints from cache
        $result = $newRouter->match('GET', '/profile/abc');
        $this->assertNull($result);
    }

    /**
     * Test file cache implementation
     */
    public function testFileCacheImplementation(): void
    {
        // Create a temporary directory for cache files
        $tempDir = sys_get_temp_dir() . '/router_cache_test_' . uniqid();
        mkdir($tempDir);

        try {
            $fileCache = new \Gwack\Router\FileCache($tempDir);

            // Test basic cache operations
            $fileCache->set('test_key', 'test_value');
            $this->assertTrue($fileCache->has('test_key'));
            $this->assertEquals('test_value', $fileCache->get('test_key'));

            $fileCache->delete('test_key');
            $this->assertFalse($fileCache->has('test_key'));

            // Test with router
            $router = new Router(new RouteCollection(), $fileCache);
            $router->get('/cached', fn() => 'cached route');

            // Match to trigger cache
            $router->match('GET', '/cached');

            // Create a new router with the same file cache
            $newRouter = new Router(new RouteCollection(), $fileCache);
            $newRouter->get('/cached', fn() => 'cached route');

            // Should use cached routes
            $result = $newRouter->match('GET', '/cached');
            $this->assertNotNull($result);
        } finally {
            // Clean up temp directory
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * Helper method to recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
