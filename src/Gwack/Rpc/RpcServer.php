<?php

namespace Gwack\Rpc;

use Gwack\Rpc\Interfaces\RpcServerInterface;
use Gwack\Rpc\Interfaces\SerializerInterface;
use Gwack\Rpc\Interfaces\MiddlewareInterface;
use Gwack\Rpc\Interfaces\RpcHandlerInterface;
use Gwack\Rpc\Serializers\JsonSerializer;
use Gwack\Rpc\Handlers\ServiceHandler;
use Gwack\Rpc\Exceptions\RpcException;
use Gwack\Rpc\Exceptions\InvalidRequestException;
use Gwack\Rpc\Exceptions\MethodNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Container\ContainerInterface;

/**
 * RPC server implementation
 *
 * @package Gwack\Rpc
 */
class RpcServer implements RpcServerInterface
{
    /**
     * @var array Registered services
     */
    private array $services = [];

    /**
     * @var array Registered handlers
     */
    private array $handlers = [];

    /**
     * @var SerializerInterface The serializer
     */
    private SerializerInterface $serializer;

    /**
     * @var array Middleware stack
     */
    private array $middleware = [];

    /**
     * @var ContainerInterface|null Dependency injection container
     */
    private ?ContainerInterface $container;

    /**
     * @var array Performance statistics
     */
    private array $stats = [
        'requests_total' => 0,
        'requests_successful' => 0,
        'requests_failed' => 0,
        'total_duration' => 0.0,
        'methods_called' => [],
    ];

    /**
     * @var bool Whether to collect detailed statistics
     */
    private bool $collectStats;

    /**
     * Constructor
     * 
     * @param SerializerInterface|null $serializer
     * @param ContainerInterface|null $container
     * @param bool $collectStats
     */
    public function __construct(
        ?SerializerInterface $serializer = null,
        ?ContainerInterface $container = null,
        bool $collectStats = true
    ) {
        $this->serializer = $serializer ?? new JsonSerializer();
        $this->container = $container;
        $this->collectStats = $collectStats;
    }

    /**
     * Handle an RPC request
     * 
     * @param Request $request The HTTP request containing the RPC call
     * @return Response The HTTP response with the result
     */
    public function handleRequest(Request $request): Response
    {
        $startTime = hrtime(true);

        if ($this->collectStats) {
            $this->stats['requests_total']++;
        }

        try {
            return $this->processMiddleware($request, function (Request $req) use ($startTime) {
                return $this->processRpcRequest($req, $startTime);
            });
        } catch (\Throwable $e) {
            if ($this->collectStats) {
                $this->stats['requests_failed']++;
                $this->stats['total_duration'] += (hrtime(true) - $startTime) / 1e6;
            }

            return $this->createErrorResponse($e, $request);
        }
    }

    /**
     * Register a service for RPC calls
     * 
     * @param string $name The service name
     * @param object|string $service The service instance or class name
     * @return void
     */
    public function registerService(string $name, object|string $service): void
    {
        if (is_string($service)) {
            if (!class_exists($service)) {
                throw new \InvalidArgumentException("Class '{$service}' does not exist");
            }

            if ($this->container && $this->container->has($service)) {
                $service = $this->container->get($service);
            } else {
                $service = new $service();
            }
        }

        $this->services[$name] = $service;
        $this->handlers[$name] = new ServiceHandler($service, $this->container);
    }

    /**
     * Register multiple services at once
     * 
     * @param array $services Array of service name => service mappings
     * @return void
     */
    public function registerServices(array $services): void
    {
        foreach ($services as $name => $service) {
            $this->registerService($name, $service);
        }
    }

    /**
     * Check if a service is registered
     * 
     * @param string $name The service name
     * @return bool
     */
    public function hasService(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * Get registered service names
     * 
     * @return array
     */
    public function getServiceNames(): array
    {
        return array_keys($this->services);
    }

    /**
     * Set the serializer for request/response handling
     * 
     * @param SerializerInterface $serializer
     * @return void
     */
    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    /**
     * Add middleware for request/response processing
     * 
     * @param MiddlewareInterface $middleware
     * @return void
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Get performance statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset performance statistics
     * 
     * @return void
     */
    public function resetStats(): void
    {
        $this->stats = [
            'requests_total' => 0,
            'requests_successful' => 0,
            'requests_failed' => 0,
            'total_duration' => 0.0,
            'methods_called' => [],
        ];
    }

    /**
     * Process middleware stack
     * 
     * @param Request $request
     * @param callable $handler
     * @return Response
     */
    private function processMiddleware(Request $request, callable $handler): Response
    {
        $middleware = array_reverse($this->middleware);

        foreach ($middleware as $mw) {
            $handler = function (Request $req) use ($mw, $handler) {
                return $mw->handle($req, $handler);
            };
        }

        return $handler($request);
    }

    /**
     * Process the actual RPC request
     * 
     * @param Request $request
     * @param int $startTime
     * @return Response
     */
    private function processRpcRequest(Request $request, int $startTime): Response
    {
        // Parse the RPC request
        $rpcRequest = $this->parseRequest($request);

        // Extract method components
        [$serviceName, $methodName] = $this->parseMethodName($rpcRequest['method']);

        // Get the handler
        if (!isset($this->handlers[$serviceName])) {
            throw new MethodNotFoundException("Service '{$serviceName}' not found");
        }

        $handler = $this->handlers[$serviceName];

        // Call the method
        $result = $handler->handleCall($methodName, $rpcRequest['params'] ?? []);

        // Track statistics
        if ($this->collectStats) {
            $this->stats['requests_successful']++;
            $this->stats['total_duration'] += (hrtime(true) - $startTime) / 1e6;

            $fullMethod = $rpcRequest['method'];
            if (!isset($this->stats['methods_called'][$fullMethod])) {
                $this->stats['methods_called'][$fullMethod] = 0;
            }
            $this->stats['methods_called'][$fullMethod]++;
        }

        // Create successful response
        return $this->createSuccessResponse($result, $rpcRequest['id'] ?? null);
    }

    /**
     * Parse RPC request from HTTP request
     * 
     * @param Request $request
     * @return array
     * @throws InvalidRequestException
     */
    private function parseRequest(Request $request): array
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (!$this->serializer->supports($contentType)) {
            throw new InvalidRequestException("Unsupported content type: {$contentType}");
        }

        $body = $request->getContent();
        if (empty($body)) {
            throw new InvalidRequestException('Empty request body');
        }

        try {
            $data = $this->serializer->deserialize($body);
        } catch (\Throwable $e) {
            throw new InvalidRequestException('Invalid request format: ' . $e->getMessage());
        }

        // Validate required fields
        if (!is_array($data) || !isset($data['method'])) {
            throw new InvalidRequestException('Missing required field: method');
        }

        return $data;
    }

    /**
     * Parse method name into service and method components
     * 
     * @param string $methodName
     * @return array [serviceName, methodName]
     * @throws MethodNotFoundException
     */
    private function parseMethodName(string $methodName): array
    {
        if (str_contains($methodName, '.')) {
            return explode('.', $methodName, 2);
        }

        // If no service specified, look for a default service
        if (count($this->services) === 1) {
            return [array_key_first($this->services), $methodName];
        }

        throw new MethodNotFoundException("Method '{$methodName}' must specify service (e.g., 'service.method')");
    }

    /**
     * Create successful response
     * 
     * @param mixed $result
     * @param mixed $id
     * @return Response
     */
    private function createSuccessResponse(mixed $result, mixed $id = null): Response
    {
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => $result,
        ];

        if ($id !== null) {
            $responseData['id'] = $id;
        }

        $content = $this->serializer->serialize($responseData);

        return new Response(
            $content,
            200,
            [
                'Content-Type' => $this->serializer->getContentType(),
                'Content-Length' => strlen($content),
            ]
        );
    }

    /**
     * Create error response
     * 
     * @param \Throwable $error
     * @param Request $request
     * @return Response
     */
    private function createErrorResponse(\Throwable $error, Request $request): Response
    {
        $errorData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $error instanceof RpcException ? $error->getRpcCode() : -32603,
                'message' => $error->getMessage(),
            ],
        ];

        // Try to extract ID from request if possible
        try {
            $body = $request->getContent();
            if ($body) {
                $requestData = $this->serializer->deserialize($body);
                if (is_array($requestData) && isset($requestData['id'])) {
                    $errorData['id'] = $requestData['id'];
                }
            }
        } catch (\Throwable) {
            // Ignore parsing errors when creating error response
        }

        if ($error instanceof RpcException && $error->getErrorData() !== null) {
            $errorData['error']['data'] = $error->getErrorData();
        }

        $content = $this->serializer->serialize($errorData);

        return new Response(
            $content,
            200, // RPC errors are still HTTP 200
            [
                'Content-Type' => $this->serializer->getContentType(),
                'Content-Length' => strlen($content),
            ]
        );
    }
}
