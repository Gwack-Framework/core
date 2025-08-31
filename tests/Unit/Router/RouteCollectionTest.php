<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Gwack\Router\Route;
use Gwack\Router\RouteCollection;

class RouteCollectionTest extends TestCase
{
    /**
     * Test adding routes to the collection
     */
    public function testAddRoute(): void
    {
        $collection = new RouteCollection();

        $route1 = new Route('GET', '/users', fn() => 'users');
        $route2 = new Route('POST', '/users', fn() => 'create user');

        $collection->add($route1, 'users.index');
        $collection->add($route2, 'users.create');

        $this->assertCount(2, $collection->all());
        $this->assertSame($route1, $collection->get('users.index'));
        $this->assertSame($route2, $collection->get('users.create'));
    }

    /**
     * Test named routes in collection
     */
    public function testNamedRoutes(): void
    {
        $collection = new RouteCollection();

        $route = new Route('GET', '/profile/{id}', fn($id) => "Profile $id");
        $collection->add($route, 'profile');

        $this->assertTrue($collection->has('profile'));
        $this->assertFalse($collection->has('unknown'));
        $this->assertSame($route, $collection->get('profile'));
    }

    /**
     * Test filtering routes by method
     */
    public function testRoutesByMethod(): void
    {
        $collection = new RouteCollection();

        $getRoute = new Route('GET', '/users', fn() => 'get users');
        $postRoute = new Route('POST', '/users', fn() => 'create user');
        $putRoute = new Route('PUT', '/users/{id}', fn($id) => "update user $id");

        $collection->add($getRoute);
        $collection->add($postRoute);
        $collection->add($putRoute);

        $getRoutes = $collection->getByMethod('GET');
        $this->assertCount(1, $getRoutes);
        $this->assertSame($getRoute, $getRoutes[0]);

        $putRoutes = $collection->getByMethod('PUT');
        $this->assertCount(1, $putRoutes);
        $this->assertSame($putRoute, $putRoutes[0]);

        $deleteRoutes = $collection->getByMethod('DELETE');
        $this->assertEmpty($deleteRoutes);
    }

    /**
     * Test static routes optimization
     */
    public function testStaticRoutes(): void
    {
        $collection = new RouteCollection();

        $staticRoute = new Route('GET', '/about', fn() => 'about page');
        $dynamicRoute = new Route('GET', '/users/{id}', fn($id) => "user $id");

        $collection->add($staticRoute);
        $collection->add($dynamicRoute);

        $staticRoutes = $collection->getStaticRoutes();
        $this->assertArrayHasKey('GET', $staticRoutes);
        $this->assertArrayHasKey('/about', $staticRoutes['GET']);
        $this->assertSame($staticRoute, $staticRoutes['GET']['/about']);
    }

    /**
     * Test where() method for custom parameter patterns
     */
    public function testWhereMethod(): void
    {
        $collection = new RouteCollection();

        $route = new Route('GET', '/users/{id}', fn($id) => "user $id");
        $collection->add($route, 'user.show');

        // Apply custom regex constraint
        $collection->where('id', '\d+');

        // Test matching
        $result = $collection->match('GET', '/users/123');
        $this->assertNotNull($result);
        [$matchedRoute, $params] = $result;
        $this->assertSame($route, $matchedRoute);
        $this->assertSame(['id' => '123'], $params);

        // Test non-matching
        $result = $collection->match('GET', '/users/abc');
        $this->assertNull($result);
    }

    /**
     * Test whereMultiple() method
     */
    public function testWhereMultipleMethod(): void
    {
        $collection = new RouteCollection();

        $route = new Route(
            'GET',
            '/api/{version}/users/{id}',
            fn($version, $id) => "API v$version user $id"
        );

        $collection->add($route, 'api.user');

        // Apply multiple constraints
        $collection->whereMultiple([
            'version' => 'v\d+',
            'id' => '\d+'
        ]);

        // Test valid route
        $result = $collection->match('GET', '/api/v1/users/123');
        $this->assertNotNull($result);

        // Test invalid version
        $result = $collection->match('GET', '/api/version1/users/123');
        $this->assertNull($result);

        // Test invalid user id
        $result = $collection->match('GET', '/api/v1/users/abc');
        $this->assertNull($result);
    }
}
