<?php

namespace Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use Gwack\Api\ApiServer;
use Gwack\Api\Serializers\JsonSerializer;
use Gwack\Api\Middleware\CorsMiddleware;
use Gwack\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for complete API workflows
 */
class ApiIntegrationTest extends TestCase
{
    private ApiServer $apiServer;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $serializer = new JsonSerializer();
        $this->apiServer = new ApiServer($serializer, [], $this->container);

        // Add CORS middleware
        $this->apiServer->addMiddleware(new CorsMiddleware());
    }

    /**
     * Test complete REST API workflow
     */
    public function testCompleteRestApiWorkflow(): void
    {
        // Set up a simple user resource
        $users = [];
        $nextId = 1;

        // GET /users (list)
        $this->apiServer->get('/users', function () use (&$users) {
            return ['users' => array_values($users)];
        });

        // POST /users (create)
        $this->apiServer->post('/users', function (Request $request, array $data) use (&$users, &$nextId) {
            $user = [
                'id' => $nextId++,
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? ''
            ];
            $users[$user['id']] = $user;
            return ['user' => $user];
        });

        // GET /users/{id} (show)
        $this->apiServer->get('/users/{id}', function (Request $request) use (&$users) {
            $id = (int) $request->attributes->get('id');
            if (!isset($users[$id])) {
                throw new \Gwack\Api\Exceptions\NotFoundException('User not found');
            }
            return ['user' => $users[$id]];
        });

        // PUT /users/{id} (update)
        $this->apiServer->put('/users/{id}', function (Request $request, array $data) use (&$users) {
            $id = (int) $request->attributes->get('id');
            if (!isset($users[$id])) {
                throw new \Gwack\Api\Exceptions\NotFoundException('User not found');
            }
            $users[$id] = array_merge($users[$id], $data);
            return ['user' => $users[$id]];
        });

        // DELETE /users/{id} (destroy)
        $this->apiServer->delete('/users/{id}', function (Request $request) use (&$users) {
            $id = (int) $request->attributes->get('id');
            if (!isset($users[$id])) {
                throw new \Gwack\Api\Exceptions\NotFoundException('User not found');
            }
            unset($users[$id]);
            return ['message' => 'User deleted'];
        });

        // Test 1: List users (initially empty)
        $request = Request::create('/api/v1/users', 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEmpty($data['users']);

        // Test 2: Create a user
        $userData = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $request = Request::create(
            '/api/v1/users',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($userData)
        );
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Doe', $data['user']['name']);
        $this->assertEquals('john@example.com', $data['user']['email']);
        $userId = $data['user']['id'];

        // Test 3: Get the created user
        $request = Request::create("/api/v1/users/$userId", 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Doe', $data['user']['name']);

        // Test 4: Update the user
        $updateData = ['name' => 'John Smith'];
        $request = Request::create(
            "/api/v1/users/$userId",
            'PUT',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('John Smith', $data['user']['name']);
        $this->assertEquals('john@example.com', $data['user']['email']); // Should preserve

        // Test 5: Delete the user
        $request = Request::create("/api/v1/users/$userId", 'DELETE');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User deleted', $data['message']);

        // Test 6: Try to get deleted user (should 404)
        $request = Request::create("/api/v1/users/$userId", 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test middleware integration
     */
    public function testMiddlewareIntegration(): void
    {
        $this->apiServer->get('/test', function () {
            return ['message' => 'test'];
        });

        $request = Request::create('/api/v1/test', 'GET');
        $request->headers->set('Origin', 'https://example.com');

        $response = $this->apiServer->handleRequest($request);

        // Check CORS headers from middleware
        $this->assertEquals('https://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Methods'));
    }

    /**
     * Test resource routing
     */
    public function testResourceRouting(): void
    {
        $controller = new class {
            public function index()
            {
                return ['articles' => []];
            }

            public function show($id)
            {
                return ['article' => ['id' => $id, 'title' => "Article $id"]];
            }

            public function store(Request $request, array $data)
            {
                return ['article' => ['id' => 1, 'title' => $data['title']]];
            }

            public function update($id, Request $request, array $data)
            {
                return ['article' => ['id' => $id, 'title' => $data['title']]];
            }

            public function destroy($id)
            {
                return ['message' => "Article $id deleted"];
            }
        };

        $this->apiServer->resource('articles', $controller);

        // Test index
        $request = Request::create('/api/v1/articles', 'GET');
        $response = $this->apiServer->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Test show
        $request = Request::create('/api/v1/articles/123', 'GET');
        $response = $this->apiServer->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('123', $data['article']['id']);

        // Test store
        $request = Request::create(
            '/api/v1/articles',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'New Article'])
        );
        $response = $this->apiServer->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('New Article', $data['article']['title']);
    }

    /**
     * Test statistics collection
     */
    public function testStatisticsCollection(): void
    {
        $this->apiServer->setStatsEnabled(true);

        $this->apiServer->get('/test', function () {
            return ['message' => 'test'];
        });

        // Make several requests
        for ($i = 0; $i < 3; $i++) {
            $request = Request::create('/api/v1/test', 'GET');
            $this->apiServer->handleRequest($request);
        }

        $stats = $this->apiServer->getStats();
        $this->assertEquals(3, $stats['requests_total']);
        $this->assertEquals(3, $stats['requests_successful']);
        $this->assertEquals(0, $stats['requests_failed']);
    }

    /**
     * Test error handling
     */
    public function testErrorHandling(): void
    {
        $this->apiServer->get('/error', function () {
            throw new \RuntimeException('Something went wrong');
        });

        $request = Request::create('/api/v1/error', 'GET');
        $response = $this->apiServer->handleRequest($request);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['error']);
        $this->assertEquals('Something went wrong', $data['message']);
    }
}
