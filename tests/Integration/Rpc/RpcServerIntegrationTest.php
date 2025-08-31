<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Gwack\Rpc\RpcServer;
use Gwack\Rpc\Handlers\ServiceHandler;
use Gwack\Rpc\Middleware\CorsMiddleware;
use Gwack\Rpc\Middleware\LoggingMiddleware;
use Gwack\Rpc\Serializers\JsonSerializer;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Integration tests for RPC Server
 */
class RpcServerIntegrationTest extends TestCase
{
    private RpcServer $server;
    private SimpleTestLogger $logger;

    protected function setUp(): void
    {
        $this->server = new RpcServer(new JsonSerializer());
        $this->logger = new SimpleTestLogger();
    }

    public function testCompleteRpcWorkflow(): void
    {
        // Register a service
        $mathService = new TestCalculatorService();
        $this->server->registerService('calculator', $mathService);

        // Add middleware
        $this->server->addMiddleware(new CorsMiddleware([
            'allowed_origins' => ['https://example.com'],
            'allowed_methods' => ['POST', 'OPTIONS'],
            'allow_credentials' => true,
        ]));

        $this->server->addMiddleware(new LoggingMiddleware($this->logger));

        // Create a valid JSON-RPC 2.0 request
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calculator.add',
            'params' => ['a' => 10, 'b' => 5],
            'id' => 1
        ];

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        // Process the request
        $response = $this->server->handleRequest($request);

        // Verify the response
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('2.0', $responseData['jsonrpc']);
        $this->assertSame(15, $responseData['result']);
        $this->assertSame(1, $responseData['id']);

        // Verify middleware executed
        $this->assertTrue($this->logger->hasInfoRecords());
    }

    public function testBatchRpcRequests(): void
    {
        // TODO: Implement batch request support in RpcServer
        $this->markTestSkipped('Batch requests not yet implemented');
    }

    public function testCorsPreflightRequest(): void
    {
        $this->server->addMiddleware(new CorsMiddleware([
            'allowed_origins' => ['https://spa.example.com'],
            'allowed_methods' => ['POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'max_age' => 3600,
        ]));

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'REQUEST_METHOD' => 'OPTIONS',
                'HTTP_ORIGIN' => 'https://spa.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type'
            ]
        );

        $response = $this->server->handleRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('https://spa.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type, Authorization', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertSame('3600', $response->headers->get('Access-Control-Max-Age'));
    }

    public function testServiceMethodFiltering(): void
    {
        $mathService = new TestCalculatorService();
        $this->server->registerService('math', $mathService);

        // Get the handler and configure it
        $reflection = new \ReflectionClass($this->server);
        $handlersProperty = $reflection->getProperty('handlers');
        $handlersProperty->setAccessible(true);
        $handlers = $handlersProperty->getValue($this->server);

        // Only allow addition and subtraction
        $handlers['math']->setAllowedMethods(['add', 'subtract']);

        // Test allowed method
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => ['a' => 5, 'b' => 3],
            'id' => 1
        ];

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertSame(8, $responseData['result']);

        // Test denied method
        $requestData['method'] = 'math.multiply';
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertSame(-32601, $responseData['error']['code']);
    }

    public function testErrorHandling(): void
    {
        $mathService = new TestCalculatorService();
        $this->server->registerService('calc', $mathService);

        // Test method not found
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calc.nonexistent',
            'params' => [],
            'id' => 1
        ];

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertSame(-32601, $responseData['error']['code']);
        $this->assertStringContainsString('not found', $responseData['error']['message']);

        // Test invalid parameters
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'calc.divide',
            'params' => ['a' => 10], // Missing 'b' parameter
            'id' => 2
        ];

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $response = $this->server->handleRequest($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertSame(-32602, $responseData['error']['code']);
    }

    public function testServerStatistics(): void
    {
        $mathService = new TestCalculatorService();
        $this->server->registerService('calc', $mathService);

        // Make a few requests
        for ($i = 0; $i < 3; $i++) {
            $requestData = [
                'jsonrpc' => '2.0',
                'method' => 'calc.add',
                'params' => ['a' => $i, 'b' => 1],
                'id' => $i + 1
            ];

            $request = new Request(
                [],
                [],
                [],
                [],
                [],
                ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
                json_encode($requestData)
            );

            $this->server->handleRequest($request);
        }

        $stats = $this->server->getStats();

        $this->assertSame(3, $stats['requests_total']);
        $this->assertSame(3, $stats['requests_successful']);
        $this->assertSame(0, $stats['requests_failed']);
        $this->assertGreaterThan(0, $stats['total_duration']);
    }
}

// Test service for integration testing
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

    public function divide(int $a, int $b): float
    {
        if ($b === 0) {
            throw new \InvalidArgumentException('Division by zero');
        }
        return $a / $b;
    }

    public function power(int $base, int $exponent = 2): int
    {
        return pow($base, $exponent);
    }

    public function sqrt(float $number): float
    {
        if ($number < 0) {
            throw new \InvalidArgumentException('Cannot calculate square root of negative number');
        }
        return sqrt($number);
    }
}

// Simple test logger for integration tests
class SimpleTestLogger implements LoggerInterface
{
    private array $logs = [];

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
            'timestamp' => microtime(true),
        ];
    }

    public function hasInfoRecords(): bool
    {
        foreach ($this->logs as $log) {
            if ($log['level'] === LogLevel::INFO) {
                return true;
            }
        }
        return false;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
