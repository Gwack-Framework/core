<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Router\Route;

/**
 * Tests for the Route class
 */
class RouteTest extends TestCase
{
    /**
     * Test route constructor and getters
     */
    public function testRouteBasics(): void
    {
        $handler = function () {
            return 'test';
        };
        $route = new Route('GET', '/test', $handler);

        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/test', $route->getPath());
        $this->assertSame($handler, $route->getHandler());
    }

    /**
     * Test static route matching
     */
    public function testStaticRouteMatching(): void
    {
        $handler = function () {
            return 'test';
        };
        $route = new Route('GET', '/test', $handler);

        $this->assertTrue($route->isStatic());
        $this->assertIsArray($route->matches('/test'));
        $this->assertFalse($route->matches('/test/123'));
    }

    /**
     * Test route with parameters
     */
    public function testRouteWithParameters(): void
    {
        $handler = function ($id) {
            return "user $id";
        };
        $route = new Route('GET', '/users/{id}', $handler);

        $this->assertFalse($route->isStatic());

        // Match should extract parameter
        $params = $route->matches('/users/123');
        $this->assertIsArray($params);
        $this->assertArrayHasKey('id', $params);
        $this->assertEquals('123', $params['id']);

        // Should not match different path
        $this->assertFalse($route->matches('/users'));
        $this->assertFalse($route->matches('/users/123/edit'));
    }

    /**
     * Test route with custom regex constraints
     */
    public function testRouteWithCustomRegex(): void
    {
        $handler = function ($year) {
            return "year $year";
        };
        $route = new Route('GET', '/archive/{year:\d{4}}', $handler);

        $this->assertFalse($route->isStatic());

        // Should match with valid year
        $params = $route->matches('/archive/2023');
        $this->assertIsArray($params);
        $this->assertArrayHasKey('year', $params);
        $this->assertEquals('2023', $params['year']);

        // Should not match with invalid year
        $this->assertFalse($route->matches('/archive/abc'));
    }

    /**
     * Test route with multiple parameters
     */
    public function testRouteWithMultipleParameters(): void
    {
        $handler = function ($id, $postId) {
            return "user $id, post $postId";
        };
        $route = new Route('GET', '/users/{id}/posts/{postId}', $handler);

        $this->assertFalse($route->isStatic());

        // Should match and extract both parameters
        $params = $route->matches('/users/123/posts/456');
        $this->assertIsArray($params);
        $this->assertEquals('123', $params['id']);
        $this->assertEquals('456', $params['postId']);
    }

    /**
     * Test route parameter names
     */
    public function testRouteParameterNames(): void
    {
        $handler = function ($id, $postId) {
            return "user $id, post $postId";
        };
        $route = new Route('GET', '/users/{id}/posts/{postId}', $handler);

        $paramNames = $route->getParameterNames();
        $this->assertCount(2, $paramNames);
        $this->assertEquals(['id', 'postId'], $paramNames);
    }

    /**
     * Test compile method generates valid regex pattern
     */
    public function testRouteCompilation(): void
    {
        $handler = function ($id) {
            return "user $id";
        };
        $route = new Route('GET', '/users/{id}', $handler);

        $compiledPattern = $route->getCompiledPattern();
        $this->assertNotEmpty($compiledPattern);

        // Check that the compiled pattern is a valid regex and works as expected
        $this->assertEquals(1, preg_match($compiledPattern, '/users/123'));
        $this->assertEquals(0, preg_match($compiledPattern, '/users'));
    }
}
