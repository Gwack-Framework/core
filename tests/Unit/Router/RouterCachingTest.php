<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Gwack\Router\Router;
use Gwack\Router\Route;
use Gwack\Router\ArrayCache;
use Gwack\Router\FileCache;
use Gwack\Router\RouteCollection;

class RouterCachingTest extends TestCase
{
    /**
     * Test caching with ArrayCache
     */
    public function testArrayCache(): void
    {
        $cache = new ArrayCache();
        $router = new Router(null, $cache);

        // Add some routes
        $router->get('/users', fn() => 'users list');
        $router->post('/users', fn() => 'create user');
        $router->get('/users/{id}', fn($id) => "user $id")
            ->where('id', '\d+');

        // First compilation should build everything
        $result = $router->match('GET', '/users/123');
        $this->assertNotNull($result);

        // Create a new router with the same cache
        $newRouter = new Router(null, $cache);

        // Add the SAME routes for cache reuse
        $newRouter->get('/users', fn() => 'different handler');
        $newRouter->post('/users', fn() => 'different create user');
        $newRouter->get('/users/{id}', fn($id) => "different user $id")
            ->where('id', '\d+');

        // Should work with cached compilation
        $result = $newRouter->match('GET', '/users');
        $this->assertNotNull($result);

        // ID route with regex constraint still works
        $result = $newRouter->match('GET', '/users/123');
        $this->assertNotNull($result);
        $result = $newRouter->match('GET', '/users/abc');
        $this->assertNull($result);
    }

    /**
     * Test route compilation with custom patterns
     */
    public function testCustomPatternCompilation(): void
    {
        $router = new Router();

        // Add route with a parameter
        $router->get('/users/{id}/posts/{post_id}', fn($id, $postId) => "user $id, post $postId");

        // Apply custom patterns
        $router->where('id', '\d+');
        $router->where('post_id', '[a-f0-9]+');

        // Test valid routes
        $result = $router->match('GET', '/users/123/posts/abc123');
        $this->assertNotNull($result);

        // Test invalid id
        $result = $router->match('GET', '/users/abc/posts/abc123');
        $this->assertNull($result);

        // Test invalid post_id
        $result = $router->match('GET', '/users/123/posts/xyz');
        $this->assertNull($result);
    }

    /**
     * Test using named routes
     */
    public function testNamedRoutes(): void
    {
        $router = new Router();

        // Add named route
        $router->addNamedRoute('GET', '/dashboard', fn() => 'dashboard', 'admin.dashboard');

        // Test retrieval by name
        $route = $router->getNamedRoute('admin.dashboard');
        $this->assertNotNull($route);
        $this->assertEquals('/dashboard', $route->getPath());

        // Test matching
        $result = $router->match('GET', '/dashboard');
        $this->assertNotNull($result);
    }

    /**
     * Test route collection integration
     */
    public function testRouteCollection(): void
    {
        $collection = new RouteCollection();
        $router = new Router($collection);

        // Add routes to router
        $router->get('/api/users', fn() => 'users api');

        // Add routes directly to collection
        $handler = fn($id) => "product $id";
        $route = new Route('GET', '/api/products/{id}', $handler);
        $collection->add($route, 'api.products.show');

        // Test router matching
        $result = $router->match('GET', '/api/users');
        $this->assertNotNull($result);

        // Test collection route matching
        $result = $router->match('GET', '/api/products/123');
        $this->assertNotNull($result);

        // Test named route retrieval
        $this->assertNotNull($router->getNamedRoute('api.products.show'));
    }
}
