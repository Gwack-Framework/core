<?php

namespace Gwack\Api;

use Gwack\Api\Interfaces\MiddlewareInterface;
use Gwack\Api\Interfaces\SerializerInterface;
use Gwack\Api\Interfaces\ValidatorInterface;
use Gwack\Api\Exceptions\ApiException;
use Gwack\Api\Exceptions\ValidationException;
use Gwack\Api\Exceptions\NotFoundException;
use Gwack\Api\Exceptions\MethodNotAllowedException;
use Gwack\Router\Router;
use Gwack\Router\RouteCollection;
use Gwack\Router\Interfaces\RouterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * REST API Server
 *
 * @package Gwack\Api
 */
class ApiServer
{
    private RouterInterface $router;
    private SerializerInterface $serializer;
    private ?ValidatorInterface $validator = null;
    private ?ContainerInterface $container = null;
    private LoggerInterface $logger;

    /**
     * @var MiddlewareInterface[] Middleware stack
     */
    private array $middleware = [];

    /**
     * @var array API configuration
     */
    private array $config = [
        'base_path' => '/api',
        'version' => 'v1',
        'content_types' => ['application/json', 'application/xml'],
        'default_content_type' => 'application/json',
        'cors_enabled' => true,
        'rate_limiting' => false,
        'debug' => false,
    ];

    /**
     * @var array Performance statistics
     */
    private array $stats = [
        'requests_total' => 0,
        'requests_successful' => 0,
        'requests_failed' => 0,
        'total_duration' => 0.0,
        'endpoints_called' => [],
    ];

    /**
     * @var bool Whether to collect statistics
     */
    private bool $collectStats = true;

    /**
     * Constructor
     *
     * @param SerializerInterface $serializer The serializer for request/response data
     * @param array $config API configuration options
     * @param ContainerInterface|null $container DI container for controller resolution
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(
        SerializerInterface $serializer,
        array $config = [],
        ?ContainerInterface $container = null,
        ?LoggerInterface $logger = null
    ) {
        $this->router = new Router(new RouteCollection());
        $this->serializer = $serializer;
        $this->container = $container;
        $this->logger = $logger ?? new NullLogger();
        $this->config = array_merge($this->config, $config);

        $this->initializeStats();
    }

    /**
     * Set a custom router instance
     *
     * @param RouterInterface $router
     * @return void
     */
    public function setRouter(RouterInterface $router): void
    {
        $this->router = $router;
    }

    /**
     * Get the router instance
     *
     * @return RouterInterface
     */
    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * Set validator instance
     *
     * @param ValidatorInterface $validator
     * @return void
     */
    public function setValidator(ValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * Add middleware to the stack
     *
     * @param MiddlewareInterface $middleware
     * @return void
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Register a GET endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function get(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->get($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register a POST endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string|array $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function post(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->post($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register a PUT endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string|array $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function put(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->put($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register a PATCH endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string|array $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function patch(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->patch($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register a DELETE endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string|array $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function delete(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->delete($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register an OPTIONS endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string|array $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function options(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->options($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register a HEAD endpoint
     *
     * @param string $path URL path pattern
     * @param callable|string|array $handler Request handler
     * @param array $options Additional options
     * @return self
     */
    public function head(string $path, callable|string|array $handler, array $options = []): self
    {
        $this->router->head($this->preparePath($path), $this->prepareHandler($handler), $options);
        return $this;
    }

    /**
     * Register a resource with full CRUD operations
     *
     * @param string $resource Resource name (e.g., 'users')
     * @param string|object $controller Controller class or instance
     * @param array $options Resource options
     * @return self
     */
    public function resource(string $resource, string|object $controller, array $options = []): self
    {
        $basePath = "/{$resource}";
        $itemPath = $basePath . '/{id}';

        $only = $options['only'] ?? ['index', 'show', 'store', 'update', 'destroy'];
        $except = $options['except'] ?? [];

        $actions = array_diff($only, $except);

        if (in_array('index', $actions)) {
            $this->get($basePath, $this->prepareControllerMethod($controller, 'index'));
        }

        if (in_array('show', $actions)) {
            $this->get($itemPath, $this->prepareControllerMethod($controller, 'show'));
        }

        if (in_array('store', $actions)) {
            $this->post($basePath, $this->prepareControllerMethod($controller, 'store'));
        }

        if (in_array('update', $actions)) {
            $this->put($itemPath, $this->prepareControllerMethod($controller, 'update'));
            $this->patch($itemPath, $this->prepareControllerMethod($controller, 'update'));
        }

        if (in_array('destroy', $actions)) {
            $this->delete($itemPath, $this->prepareControllerMethod($controller, 'destroy'));
        }

        return $this;
    }

    /**
     * Create a route group with shared attributes
     *
     * @param array $attributes Group attributes (prefix, middleware, etc.)
     * @param callable $callback Callback to define routes in the group
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        // Store current state
        $originalBasePath = $this->config['base_path'];
        $originalMiddleware = $this->middleware;

        // Apply group attributes
        if (isset($attributes['prefix'])) {
            $this->config['base_path'] = rtrim($originalBasePath, '/') . '/' . ltrim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            foreach ((array) $attributes['middleware'] as $middleware) {
                if ($middleware instanceof MiddlewareInterface) {
                    $this->addMiddleware($middleware);
                }
            }
        }

        // Execute callback to define routes
        $callback($this);

        // Restore original state
        $this->config['base_path'] = $originalBasePath;
        $this->middleware = $originalMiddleware;
    }

    /**
     * Handle incoming HTTP request
     *
     * @param Request $request The HTTP request
     * @return SymfonyResponse The HTTP response
     */
    public function handleRequest(Request $request): SymfonyResponse
    {
        $startTime = hrtime(true);

        if ($this->collectStats) {
            $this->stats['requests_total']++;
        }

        try {
            return $this->processMiddleware($request, function (Request $req) use ($startTime) {
                return $this->processRequest($req, $startTime);
            });
        } catch (\Throwable $e) {
            if ($this->collectStats) {
                $this->stats['requests_failed']++;
                $this->stats['total_duration'] += (hrtime(true) - $startTime) / 1e6;
            }

            $this->logger->error('API request failed', [
                'exception' => $e,
                'request_uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
            ]);

            return $this->createErrorResponse($e, $request);
        }
    }

    /**
     * Get API statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset statistics
     *
     * @return void
     */
    public function resetStats(): void
    {
        $this->initializeStats();
    }

    /**
     * Enable or disable statistics collection
     *
     * @param bool $enabled
     * @return void
     */
    public function setStatsEnabled(bool $enabled): void
    {
        $this->collectStats = $enabled;
    }

    /**
     * Initialize statistics array
     *
     * @return void
     */
    private function initializeStats(): void
    {
        $this->stats = [
            'requests_total' => 0,
            'requests_successful' => 0,
            'requests_failed' => 0,
            'total_duration' => 0.0,
            'endpoints_called' => [],
        ];
    }

    /**
     * Process middleware stack
     *
     * @param Request $request
     * @param callable $handler
     * @return SymfonyResponse
     */
    private function processMiddleware(Request $request, callable $handler): SymfonyResponse
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
     * Process the API request using our router
     *
     * @param Request $request
     * @param int $startTime
     * @return SymfonyResponse
     */
    private function processRequest(Request $request, int $startTime): SymfonyResponse
    {
        // Use our optimized router to match the request
        $routeResult = $this->router->match($request->getMethod(), $request->getPathInfo());

        if ($routeResult === null) {
            throw new NotFoundException('Endpoint not found');
        }

        [$handler, $params] = $routeResult;

        // Add route parameters to request
        foreach ($params as $key => $value) {
            $request->attributes->set($key, $value);
        }

        // Parse request data
        $requestData = $this->parseRequestData($request);

        // Validate request if validator is set
        if ($this->validator !== null && !empty($requestData)) {
            $this->validateRequest($requestData, $request);
        }

        // Execute handler
        $result = $this->executeHandler($handler, $request, $requestData);

        // Track statistics
        if ($this->collectStats) {
            $this->stats['requests_successful']++;
            $this->stats['total_duration'] += (hrtime(true) - $startTime) / 1e6;

            $endpoint = $request->getMethod() . ' ' . $request->getPathInfo();
            if (!isset($this->stats['endpoints_called'][$endpoint])) {
                $this->stats['endpoints_called'][$endpoint] = 0;
            }
            $this->stats['endpoints_called'][$endpoint]++;
        }

        // Create response
        return $this->createResponse($result, $request);
    }

    /**
     * Prepare path with base path and version
     *
     * @param string $path
     * @return string
     */
    private function preparePath(string $path): string
    {
        $basePath = rtrim($this->config['base_path'], '/');
        $version = $this->config['version'];

        if ($version) {
            $basePath .= '/' . ltrim($version, '/');
        }

        return $basePath . '/' . ltrim($path, '/');
    }

    /**
     * Prepare handler for routing
     *
     * @param callable|string|array $handler
     * @return callable
     */
    private function prepareHandler(callable|string|array $handler)
    {
        if (is_string($handler)) {
            return $this->resolveControllerMethod($handler);
        }

        return $handler;
    }

    /**
     * Prepare controller method handler
     *
     * @param string|object $controller
     * @param string $method
     * @return callable
     */
    private function prepareControllerMethod(string|object $controller, string $method)
    {
        if (is_string($controller)) {
            return $this->resolveControllerMethod($controller . '@' . $method);
        }

        return [$controller, $method];
    }

    /**
     * Resolve controller method from string
     *
     * @param string $handler Format: "ControllerClass@method"
     * @return callable
     */
    private function resolveControllerMethod(string $handler)
    {
        [$controller, $method] = explode('@', $handler, 2);

        if ($this->container && $this->container->has($controller)) {
            $controllerInstance = $this->container->get($controller);
        } else {
            $controllerInstance = new $controller();
        }

        return [$controllerInstance, $method];
    }

    /**
     * Parse request data from body and query parameters
     *
     * @param Request $request
     * @return array
     */
    private function parseRequestData(Request $request): array
    {
        $data = [];

        // Parse query parameters
        $data = array_merge($data, $request->query->all());

        // Parse body content
        $content = $request->getContent();
        if (!empty($content)) {
            $contentType = $request->headers->get('Content-Type', '');

            if (str_contains($contentType, 'application/json')) {
                $bodyData = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($bodyData)) {
                    $data = array_merge($data, $bodyData);
                }
            } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($content, $bodyData);
                $data = array_merge($data, $bodyData);
            }
        }

        // Add form data
        $data = array_merge($data, $request->request->all());

        return $data;
    }

    /**
     * Validate request data
     *
     * @param array $data
     * @param Request $request
     * @return void
     * @throws ValidationException
     */
    private function validateRequest(array $data, Request $request): void
    {
        $rules = $request->attributes->get('validation_rules', []);

        if (!empty($rules)) {
            $result = $this->validator->validate($request, $rules);

            if (!empty($errors)) {
                throw new ValidationException('Validation failed', $result->getErrors());
            }
        }
    }

    /**
     * Execute request handler
     *
     * @param callable $handler
     * @param Request $request
     * @param array $data
     * @return mixed
     */
    private function executeHandler(callable $handler, Request $request, array $data): mixed
    {
        // Use reflection to determine handler parameters
        if (is_array($handler)) {
            $reflection = new \ReflectionMethod($handler[0], $handler[1]);
        } else {
            $reflection = new \ReflectionFunction($handler);
        }

        $parameters = $reflection->getParameters();
        $args = [];

        // Get route parameters from request attributes
        $routeParams = [];
        foreach ($request->attributes->all() as $key => $value) {
            if (!in_array($key, ['_route', '_controller', '_format'])) {
                $routeParams[$key] = $value;
            }
        }

        foreach ($parameters as $param) {
            $paramName = $param->getName();
            $type = $param->getType();

            // Check if this is a Request parameter
            if ($type && $type->getName() === Request::class) {
                $args[] = $request;
            }
            // Check if this parameter matches a route parameter (like 'id')
            elseif (isset($routeParams[$paramName])) {
                $args[] = $routeParams[$paramName];
            }
            // Check if this is an array type parameter for request data
            elseif ($paramName === 'data' || (!$type || $type->getName() === 'array')) {
                $args[] = $data;
            }
            // Use default value if available
            elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            }
            // Default to null
            else {
                $args[] = null;
            }
        }

        return call_user_func_array($handler, $args);
    }

    /**
     * Create successful response
     *
     * @param mixed $data
     * @param Request $request
     * @return SymfonyResponse
     */
    private function createResponse(mixed $data, Request $request): SymfonyResponse
    {
        // If the data is already a Response object, return it directly
        if ($data instanceof SymfonyResponse) {
            return $data;
        }

        // Determine response format
        $contentType = $this->negotiateContentType($request);

        // Serialize data
        $content = $this->serializer->serialize($data);

        // Create response
        $response = new SymfonyResponse($content, 200, [
            'Content-Type' => $contentType,
        ]);

        return $response;
    }

    /**
     * Create error response
     *
     * @param \Throwable $exception
     * @param Request $request
     * @return SymfonyResponse
     */
    private function createErrorResponse(\Throwable $exception, Request $request): SymfonyResponse
    {
        $statusCode = 500;
        $error = [
            'error' => true,
            'message' => $exception->getMessage(),
        ];

        if ($exception instanceof ApiException) {
            $statusCode = $exception->getStatusCode();
            $error['code'] = $exception->getCode();
        } elseif ($exception instanceof ValidationException) {
            $statusCode = 422;
            $error['validation_errors'] = $exception->getErrors();
        } elseif ($exception instanceof NotFoundException) {
            $statusCode = 404;
        } elseif ($exception instanceof MethodNotAllowedException) {
            $statusCode = 405;
        }

        // Add debug information if enabled
        if ($this->config['debug']) {
            $error['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $contentType = $this->negotiateContentType($request);
        $content = $this->serializer->serialize($error);

        return new SymfonyResponse($content, $statusCode, [
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * Negotiate content type based on Accept header and configuration
     *
     * @param Request $request
     * @return string
     */
    private function negotiateContentType(Request $request): string
    {
        $acceptHeader = $request->headers->get('Accept', '');

        // If no Accept header or accepts all, return default
        if (empty($acceptHeader) || str_contains($acceptHeader, '*/*')) {
            return $this->config['default_content_type'];
        }

        $supportedTypes = $this->config['content_types'];

        // Simple content negotiation
        foreach ($supportedTypes as $type) {
            if (str_contains($acceptHeader, $type)) {
                return $type;
            }
        }

        return $this->config['default_content_type'];
    }
}
