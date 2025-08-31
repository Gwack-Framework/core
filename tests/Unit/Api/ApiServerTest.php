<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gwack\Api\ApiServer;
use Gwack\Api\Serializers\JsonSerializer;
use Gwack\Api\Exceptions\NotFoundException;
use Gwack\Api\Exceptions\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for ApiServer
 */
class ApiServerTest extends TestCase
{
    private ApiServer $apiServer;
    private JsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
        $this->apiServer = new ApiServer($this->serializer);
    }

    /**
     * Test API server initialization
     */
    public function testApiServerInitialization(): void
    {
        $this->assertInstanceOf(ApiServer::class, $this->apiServer);
        $this->assertNotNull($this->apiServer->getRouter());
    }

    /**
     * Test adding GET routes
     */
    public function testAddGetRoute(): void
    {
        $handler = function () {
            return ['message' => 'Hello World'];
        };

        $this->apiServer->get('/test', $handler);

        $routes = $this->apiServer->getRouter()->getRoutes();
        $this->assertArrayHasKey('GET', $routes);
        $this->assertNotEmpty($routes['GET']);
    }

    /**
     * Test adding POST routes
     */
    public function testAddPostRoute(): void
    {
        $handler = function (Request $request) {
            return ['received' => 'data'];
        };

        $this->apiServer->post('/users', $handler);

        $routes = $this->apiServer->getRouter()->getRoutes();
        $this->assertArrayHasKey('POST', $routes);
        $this->assertNotEmpty($routes['POST']);
    }

    /**
     * Test adding all HTTP methods
     */
    public function testAddAllHttpMethods(): void
    {
        $handler = function () {
            return ['status' => 'ok'];
        };

        $this->apiServer->get('/test', $handler);
        $this->apiServer->post('/test', $handler);
        $this->apiServer->put('/test', $handler);
        $this->apiServer->patch('/test', $handler);
        $this->apiServer->delete('/test', $handler);
        $this->apiServer->options('/test', $handler);
        $this->apiServer->head('/test', $handler);

        $routes = $this->apiServer->getRouter()->getRoutes();

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('PUT', $routes);
        $this->assertArrayHasKey('PATCH', $routes);
        $this->assertArrayHasKey('DELETE', $routes);
        $this->assertArrayHasKey('OPTIONS', $routes);
        $this->assertArrayHasKey('HEAD', $routes);
    }

    /**
     * Test resource routing
     */
    public function testResourceRouting(): void
    {
        $controller = new class {
            public function index()
            {
                return ['users' => []];
            }
            public function show($id)
            {
                return ['user' => $id];
            }
            public function store()
            {
                return ['created' => true];
            }
            public function update($id)
            {
                return ['updated' => $id];
            }
            public function destroy($id)
            {
                return ['deleted' => $id];
            }
        };

        $this->apiServer->resource('users', $controller);

        $routes = $this->apiServer->getRouter()->getRoutes();

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('PUT', $routes);
        $this->assertArrayHasKey('DELETE', $routes);
    }

    /**
     * Test handling successful GET request
     */
    public function testHandleGetRequest(): void
    {
        $this->apiServer->get('/test', function () {
            return ['message' => 'success'];
        });

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('application/json', $contentType, "Expected JSON content type, got: $contentType");

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['message' => 'success'], $data);
    }

    /**
     * Test handling POST request with JSON data
     */
    public function testHandlePostRequestWithJsonData(): void
    {
        $this->apiServer->post('/users', function (Request $request, array $data) {
            return ['created' => $data['name']];
        });

        $requestData = ['name' => 'John Doe'];
        $request = Request::create(
            '/api/v1/users',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['created' => 'John Doe'], $data);
    }

    /**
     * Test handling request with route parameters
     */
    public function testHandleRequestWithParameters(): void
    {
        $this->apiServer->get('/users/{id}', function (Request $request) {
            $id = $request->attributes->get('id');
            return ['user_id' => $id];
        });

        $request = Request::create('/api/v1/users/123', 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['user_id' => '123'], $data);
    }

    /**
     * Test handling 404 not found
     */
    public function testHandle404NotFound(): void
    {
        $request = Request::create('/api/v1/nonexistent', 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['error']);
        $this->assertStringContainsString('not found', strtolower($data['message']));
    }

    /**
     * Test statistics collection
     */
    public function testStatisticsCollection(): void
    {
        $this->apiServer->get('/test', function () {
            return ['test' => true];
        });

        $this->apiServer->setStatsEnabled(true);

        $initialStats = $this->apiServer->getStats();
        $this->assertEquals(0, $initialStats['requests_total']);

        $request = Request::create('/api/v1/test', 'GET');
        $this->apiServer->handleRequest($request);

        $stats = $this->apiServer->getStats();
        $this->assertEquals(1, $stats['requests_total']);
        $this->assertEquals(1, $stats['requests_successful']);
        $this->assertEquals(0, $stats['requests_failed']);
    }

    /**
     * Test statistics reset
     */
    public function testStatisticsReset(): void
    {
        $this->apiServer->get('/api/v1/test', function () {
            return ['test' => true];
        });

        $request = Request::create('/api/v1/test', 'GET');
        $this->apiServer->handleRequest($request);

        $stats = $this->apiServer->getStats();
        $this->assertGreaterThan(0, $stats['requests_total']);

        $this->apiServer->resetStats();
        $stats = $this->apiServer->getStats();
        $this->assertEquals(0, $stats['requests_total']);
    }

    /**
     * Test error handling with exceptions
     */
    public function testErrorHandling(): void
    {
        $this->apiServer->get('/error', function () {
            throw new \RuntimeException('Test error');
        });

        $request = Request::create('/api/v1/error', 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['error']);
        $this->assertEquals('Test error', $data['message']);
    }
}
