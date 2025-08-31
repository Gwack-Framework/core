<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Gwack\Rpc\RpcServer;
use Gwack\Rpc\Serializers\JsonSerializer;
use Gwack\Rpc\Middleware\CorsMiddleware;
use Symfony\Component\HttpFoundation\Request;

/**
 * Comprehensive tests for the RpcServer class
 * 
 * @package Tests\Unit
 */
class RpcServerTest extends TestCase
{
    private RpcServer $server;

    protected function setUp(): void
    {
        $this->server = new RpcServer();
    }

    public function testCanCreateRpcServer(): void
    {
        $this->assertInstanceOf(RpcServer::class, $this->server);
    }

    public function testCanRegisterService(): void
    {
        $service = new TestCalculatorService();
        $this->server->registerService('calculator', $service);

        $this->assertTrue($this->server->hasService('calculator'));
        $this->assertContains('calculator', $this->server->getServiceNames());
    }

    public function testCanRegisterMultipleServices(): void
    {
        $services = [
            'calculator' => new TestCalculatorService(),
            'user' => new TestUserService(),
        ];

        $this->server->registerServices($services);

        $this->assertTrue($this->server->hasService('calculator'));
        $this->assertTrue($this->server->hasService('user'));
        $this->assertCount(2, $this->server->getServiceNames());
    }

    public function testCanHandleBasicRpcRequest(): void
    {
        $this->server->registerService('calculator', new TestCalculatorService());

        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calculator.add',
            'params' => [5, 3],
            'id' => 1
        ];

        $request = Request::create('/rpc', 'POST', [], [], [], [], json_encode($requestData));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->server->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $responseData['jsonrpc']);
        $this->assertEquals(8, $responseData['result']);
        $this->assertEquals(1, $responseData['id']);
    }

    public function testCanHandleNamedParameters(): void
    {
        $this->server->registerService('calculator', new TestCalculatorService());

        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calculator.subtract',
            'params' => ['b' => 3, 'a' => 10],
            'id' => 2
        ];

        $request = Request::create('/rpc', 'POST', [], [], [], [], json_encode($requestData));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(7, $responseData['result']);
    }

    public function testReturnsErrorForNonExistentService(): void
    {
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'nonexistent.method',
            'params' => [],
            'id' => 3
        ];

        $request = Request::create('/rpc', 'POST', [], [], [], [], json_encode($requestData));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals(-32601, $responseData['error']['code']);
    }

    public function testReturnsErrorForNonExistentMethod(): void
    {
        $this->server->registerService('calculator', new TestCalculatorService());

        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calculator.nonexistent',
            'params' => [],
            'id' => 4
        ];

        $request = Request::create('/rpc', 'POST', [], [], [], [], json_encode($requestData));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals(-32601, $responseData['error']['code']);
    }

    public function testCanAddMiddleware(): void
    {
        $middleware = new CorsMiddleware();
        $this->server->addMiddleware($middleware);

        $this->server->registerService('calculator', new TestCalculatorService());

        $request = Request::create('/rpc', 'OPTIONS');
        $response = $this->server->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testCollectsStatistics(): void
    {
        $this->server->registerService('calculator', new TestCalculatorService());

        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calculator.add',
            'params' => [1, 2],
            'id' => 5
        ];

        $request = Request::create('/rpc', 'POST', [], [], [], [], json_encode($requestData));
        $request->headers->set('Content-Type', 'application/json');

        $this->server->handleRequest($request);

        $stats = $this->server->getStats();
        $this->assertEquals(1, $stats['requests_total']);
        $this->assertEquals(1, $stats['requests_successful']);
        $this->assertEquals(0, $stats['requests_failed']);
        $this->assertArrayHasKey('calculator.add', $stats['methods_called']);
    }

    public function testCanResetStatistics(): void
    {
        $this->server->registerService('calculator', new TestCalculatorService());

        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calculator.add',
            'params' => [1, 2],
        ];

        $request = Request::create('/rpc', 'POST', [], [], [], [], json_encode($requestData));
        $request->headers->set('Content-Type', 'application/json');

        $this->server->handleRequest($request);
        $this->server->resetStats();

        $stats = $this->server->getStats();
        $this->assertEquals(0, $stats['requests_total']);
        $this->assertEquals(0, $stats['requests_successful']);
    }

    public function testHandlesInvalidJson(): void
    {
        $request = Request::create('/rpc', 'POST', [], [], [], [], 'invalid json');
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals(-32600, $responseData['error']['code']);
    }

    public function testHandlesEmptyRequest(): void
    {
        $request = Request::create('/rpc', 'POST', [], [], [], [], '');
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals(-32600, $responseData['error']['code']);
    }
}

// Test service classes
class TestCalculatorService
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }

    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }

    public function divide(float $a, float $b): float
    {
        if ($b == 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return $a / $b;
    }

    public function getHistory(): array
    {
        return ['calculations' => []];
    }
}

class TestUserService
{
    private array $users = [
        1 => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
        2 => ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
    ];

    public function getUser(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    public function getAllUsers(): array
    {
        return array_values($this->users);
    }

    public function createUser(string $name, string $email): array
    {
        $id = count($this->users) + 1;
        $user = ['id' => $id, 'name' => $name, 'email' => $email];
        $this->users[$id] = $user;
        return $user;
    }
}
