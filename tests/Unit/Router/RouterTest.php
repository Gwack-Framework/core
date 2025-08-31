<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Router\Router;

/**
 * Tests for the Router class
 */
class RouterTest extends TestCase
{
    /**
     * @var Router Router instance
     */
    private Router $router;

    /**
     * Set up before each test
     */
    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test adding a route
     */
    public function testAddRoute(): void
    {
        $handler = function () {
            return 'test';
        };
        $this->router->addRoute('GET', '/test', $handler);

        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('GET', $routes);
        $this->assertCount(1, $routes['GET']);
    }

    /**
     * Test matching static routes
     */
    public function testMatchStaticRoute(): void
    {
        $handler = function () {
            return 'test';
        };
        $this->router->get('/test', $handler);

        // Compile routes
        $this->router->compileRoutes();

        $result = $this->router->match('GET', '/test');

        $this->assertNotNull($result);
        $this->assertSame($handler, $result[0]);
        $this->assertEmpty($result[1]);
    }

    /**
     * Test matching routes with parameters
     */
    public function testMatchRouteWithParameters(): void
    {
        $handler = function ($id) {
            return "user $id";
        };
        $this->router->get('/users/{id}', $handler);

        // Compile routes
        $this->router->compileRoutes();

        $result = $this->router->match('GET', '/users/123');

        $this->assertNotNull($result);
        $this->assertSame($handler, $result[0]);
        $this->assertArrayHasKey('id', $result[1]);
        $this->assertEquals('123', $result[1]['id']);
    }

    /**
     * Test matching routes with custom regex constraints
     */
    public function testMatchRouteWithCustomRegex(): void
    {
        $handler = function ($year, $month) {
            return "$year-$month";
        };
        $this->router->get('/archive/{year:\d{4}}/months/{month:\d{2}}', $handler);

        // Compile routes
        $this->router->compileRoutes();

        // Should match with valid regex
        $result = $this->router->match('GET', '/archive/2024/months/06');
        $this->assertNotNull($result);
        $this->assertSame($handler, $result[0]);

        // Should not match with invalid regex
        $noMatch = $this->router->match('GET', '/archive/abcd/months/06');
        $this->assertNull($noMatch);
    }

    /**
     * Test that HEAD requests fall back to GET when no HEAD route is defined
     */
    public function testHeadFallsBackToGet(): void
    {
        $handler = function () {
            return 'test';
        };
        $this->router->get('/test', $handler);

        // Compile routes
        $this->router->compileRoutes();

        $result = $this->router->match('HEAD', '/test');

        $this->assertNotNull($result);
        $this->assertSame($handler, $result[0]);
    }

    /**
     * Test that OPTIONS request returns allowed methods
     */
    public function testOptionsReturnsAllowedMethods(): void
    {
        $this->router->get('/test', function () {
            return 'get';
        });
        $this->router->post('/test', function () {
            return 'post';
        });

        // Compile routes
        $this->router->compileRoutes();

        $result = $this->router->match('OPTIONS', '/test');

        $this->assertNotNull($result);
        $allowHeader = call_user_func($result[0]);

        $this->assertArrayHasKey('Allow', $allowHeader);
    }

    /**
     * Test method shortcut methods (get, post, etc.)
     */
    public function testMethodShortcuts(): void
    {
        $this->router->get('/get', function () {
            return 'get';
        });
        $this->router->post('/post', function () {
            return 'post';
        });
        $this->router->put('/put', function () {
            return 'put';
        });
        $this->router->delete('/delete', function () {
            return 'delete';
        });
        $this->router->patch('/patch', function () {
            return 'patch';
        });

        // Compile routes
        $this->router->compileRoutes();

        // Test each method
        $getResult = $this->router->match('GET', '/get');
        $postResult = $this->router->match('POST', '/post');
        $putResult = $this->router->match('PUT', '/put');
        $deleteResult = $this->router->match('DELETE', '/delete');
        $patchResult = $this->router->match('PATCH', '/patch');

        $this->assertNotNull($getResult);
        $this->assertNotNull($postResult);
        $this->assertNotNull($putResult);
        $this->assertNotNull($deleteResult);
        $this->assertNotNull($patchResult);

        $this->assertEquals('get', call_user_func($getResult[0]));
        $this->assertEquals('post', call_user_func($postResult[0]));
        $this->assertEquals('put', call_user_func($putResult[0]));
        $this->assertEquals('delete', call_user_func($deleteResult[0]));
        $this->assertEquals('patch', call_user_func($patchResult[0]));
    }

    /**
     * Test the any() method for matching multiple methods
     */
    public function testAnyMethod(): void
    {
        $handler = function () {
            return 'any';
        };
        $this->router->any('/any', $handler);

        // Compile routes
        $this->router->compileRoutes();

        // Test different methods
        $getResult = $this->router->match('GET', '/any');
        $postResult = $this->router->match('POST', '/any');
        $putResult = $this->router->match('PUT', '/any');

        $this->assertNotNull($getResult);
        $this->assertNotNull($postResult);
        $this->assertNotNull($putResult);

        $this->assertSame($handler, $getResult[0]);
        $this->assertSame($handler, $postResult[0]);
        $this->assertSame($handler, $putResult[0]);
    }

    /**
     * Test dispatching a route
     */
    public function testDispatch(): void
    {
        $this->router->get('/hello/{name}', function ($name) {
            return "Hello, $name!";
        });

        // Compile routes
        $this->router->compileRoutes();

        $result = $this->router->dispatch('GET', '/hello/world');
        $this->assertEquals('Hello, world!', $result);
    }

    /**
     * Test dispatching a non-existent route throws exception
     */
    public function testDispatchNonExistentRouteThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(404);

        $this->router->dispatch('GET', '/non-existent');
    }

    /**
     * Test trie-based routing with nested paths
     */
    public function testTrieBasedRouting(): void
    {
        $this->router->get('/api/v1/users/{id}/posts/{postId}', function ($id, $postId) {
            return "User $id, Post $postId";
        });

        // Compile routes
        $this->router->compileRoutes();

        $result = $this->router->match('GET', '/api/v1/users/123/posts/456');

        $this->assertNotNull($result);
        $this->assertEquals('User 123, Post 456', call_user_func($result[0], $result[1]['id'], $result[1]['postId']));
    }
}
