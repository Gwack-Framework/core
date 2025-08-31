<?php

namespace Gwack\Core;

use Gwack\Api\ApiServer;
use Gwack\Api\Serializers\JsonSerializer;
use Gwack\Container\Container;
use Gwack\Router\Router;
use Gwack\Http\Request;
use Gwack\Core\Resolvers\FunctionResolver;
use Gwack\Http\Validation\RuleExecutor;
use Gwack\Http\Validation\RequestValidator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Framework Application Core
 *
 * The main application class that coordinates all framework components
 * and provides the foundation for file-based routing, auto-compilation,
 * and full-stack development experience.
 *
 * @package Gwack\Core
 */
class Application
{
    /**
     * @var Container The dependency injection container instance
     */
    private Container $container;

    /**
     * @var ApiServer The API server instance
     */
    private ApiServer $apiServer;

    /**
     * @var Router The router instance
     */
    private Router $router;

    /**
     * @var array Application configuration
     */
    private array $config;

    /**
     * @var string Base path of the application
     */
    private string $basePath;

    /**
     * @var string Server path where application files are located
     */
    private string $serverPath;

    /**
     * @var string Path to the compiled distribution files
     */
    private string $distPath;

    /**
     * @var bool Whether the application has been compiled
     */
    private bool $compiled = false;

    /**
     * @var bool Whether the application has been booted
     */
    private bool $booted = false;

    /**
     * Application constructor
     *
     * @param string $basePath Application base path
     * @param array $config Application configuration
     */
    public function __construct(string $basePath, array $config = [])
    {
        $this->basePath = rtrim($basePath, '/');
        $this->serverPath = $this->basePath . '/server';
        $this->distPath = $this->basePath . '/.gwack';

        $this->config = array_merge([
            'debug' => false,
            'compile' => true,
            'api_prefix' => '/api',
            'api_version' => 'v1',
            'auto_discover' => true,
            'middleware' => [],
            'session' => [
                'driver' => 'file',
                'path' => $this->basePath . '/storage/sessions'
            ],
            'database' => [
                'driver' => 'sqlite',
                'path' => $this->basePath . '/database/app.db'
            ]
        ], $config);

        // Load helper functions
        require_once __DIR__ . '/helpers.php';

        $this->initializeContainer();
        $this->initializeApiServer();
        $this->initializeServices();
    }

    /**
     * Initialize the dependency injection container
     * 
     * @return void
     */
    private function initializeContainer(): void
    {
        $this->container = new Container();

        // Register core services
        $this->container->bind('config', function () {
            return $this->config;
        }, true);
        $this->container->bind('app', $this, true);
        $this->container->bind(Container::class, $this->container, true);
        $this->container->bind(Application::class, $this, true);
    }

    /**
     * Initialize the API server with our optimized components
     * 
     * @return void
     */
    private function initializeApiServer(): void
    {
        $serializer = new JsonSerializer();
        $this->router = new Router();

        $this->apiServer = new ApiServer($serializer, [
            'base_path' => $this->config['api_prefix'],
            'version' => $this->config['api_version'],
            'debug' => $this->config['debug'],
        ], $this->container);

        $this->apiServer->setRouter($this->router);

        // Register API server in container
        $this->container->bind(ApiServer::class, $this->apiServer, true);
        $this->container->bind(Router::class, $this->router, true);
        $this->container->bind('api', $this->apiServer, true);
        $this->container->bind('router', $this->router, true);
    }

    /**
     * Initialize framework services
     * 
     * @return void
     */
    private function initializeServices(): void
    {
        // Session service
        $this->container->bind('session', function () {
            return new Session\SessionManager($this->config['session']);
        }, true);

        // Context service (provides access to all application services)
        $this->container->bind('context', function () {
            return new Context($this->container);
        }, true);

        // Route compiler service
        $this->container->bind('compiler', function () {
            return new Compiler\RouteCompiler($this->basePath, $this->config);
        }, true);

        // Function resolver service
        $this->container->bind('functions', function () {
            return new \Gwack\Core\Resolvers\FunctionResolver($this->container);
        }, true);

        // Validation services
        $this->container->bind('validation.rule_executor', function () {
            return new RuleExecutor();
        }, true);

        $this->container->bind('validation.request_validator', function () {
            return new RequestValidator($this->container->get('validation.rule_executor'));
        }, true);

        // Register shorthand aliases for validation services
        $this->container->bind('validator', function () {
            return $this->container->get('validation.request_validator');
        }, true);
    }

    /**
     * Boot the application
     * 
     * @return self
     */
    public function boot(): self
    {
        if ($this->config['auto_discover']) {
            $this->discoverRoutes();
        }

        if ($this->config['compile'] && !$this->compiled) {
            $this->compile();
        }

        $this->booted = true;

        return $this;
    }

    /**
     * Auto-discover routes from the server directory
     * 
     * @return void
     */
    private function discoverRoutes(): void
    {
        if (!is_dir($this->serverPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->serverPath)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $this->registerRouteFile($file->getPathname());
            }
        }
    }

    /**
     * Register a route file
     * 
     * @param string $filePath
     * @return void
     */
    private function registerRouteFile(string $filePath): void
    {
        $relativePath = str_replace($this->serverPath, '', $filePath);
        $routePath = $this->convertFilePathToRoute($relativePath);

        // Create a closure that will handle the route
        $handler = function (Request $request) use ($filePath) {
            return $this->executeRouteFile($filePath, $request);
        };

        // Register for multiple HTTP methods (will be optimized during compilation)
        $this->apiServer->get($routePath, $handler);
        $this->apiServer->post($routePath, $handler);
        $this->apiServer->put($routePath, $handler);
        $this->apiServer->patch($routePath, $handler);
        $this->apiServer->delete($routePath, $handler);
    }

    /**
     * Convert file path to route pattern
     * 
     * @param string $filePath
     * @return string
     */
    private function convertFilePathToRoute(string $filePath): string
    {
        // Remove leading slash and .php extension
        $route = ltrim($filePath, '/');
        $route = preg_replace('/\.php$/', '', $route);

        // Convert /index to /
        $route = preg_replace('/\/index$/', '', $route);

        // Convert [param] to {param} for our router
        $route = preg_replace('/\[([^\]]+)\]/', '{$1}', $route);

        return '/' . ltrim($route, '/');
    }

    /**
     * Execute a route file
     * 
     * @param string $filePath
     * @param Request $request
     * @return mixed
     */
    private function executeRouteFile(string $filePath, Request $request)
    {
        // Create execution context
        $context = $this->container->get('context');

        // Setup global functions for the route file
        $this->setupRouteGlobals($context, $request);

        // Execute the route file
        ob_start();
        $result = include $filePath;
        $output = ob_get_clean();

        // If the file echoed content, return it
        if (!empty($output)) {
            return $output;
        }

        return $result;
    }

    /**
     * Setup global functions and variables for route files
     * 
     * @param Context $context
     * @param Request $request
     * @return void
     */
    private function setupRouteGlobals(Context $context, Request $request): void
    {
        // Make context and request available globally
        $GLOBALS['context'] = $context;
        $GLOBALS['request'] = $request;

        // Define global helper functions
        if (!function_exists('defineRoute')) {
            function defineRoute(callable $handler)
            {
                return call_user_func($handler, $GLOBALS['context'], $GLOBALS['request']);
            }
        }

        if (!function_exists('json')) {
            function json($data, int $status = 200)
            {
                return new \Symfony\Component\HttpFoundation\JsonResponse($data, $status);
            }
        }
    }

    /**
     * Compile the application for production
     * 
     * @return void
     */
    private function compile(): void
    {
        $compiler = $this->container->get('compiler');
        $compiler->compile();
        $this->compiled = true;
    }

    /**
     * Handle an HTTP request
     * 
     * @param Request|null $request
     * @return Response
     */
    public function handle(?Request $request = null): Response
    {
        if ($request === null) {
            $request = Request::createFromGlobals();
        }

        return $this->apiServer->handleRequest($request);
    }

    /**
     * Run the application
     * 
     * @return void
     */
    public function run(): void
    {
        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    /**
     * Get the container instance
     * 
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the API server instance
     * 
     * @return ApiServer
     */
    public function getApiServer(): ApiServer
    {
        return $this->apiServer;
    }

    /**
     * Get the router instance
     * 
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get application configuration
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get application base path
     * 
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Configure the application with additional settings
     *
     * @param array $config Configuration array to merge
     * @return self
     */
    public function configure(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Add a route to the router
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param callable $handler Route handler
     * @return self
     */
    public function addRoute(string $method, string $path, callable $handler): self
    {
        // Debug: Log which router is being used for manual routes
        error_log("Application addRoute - Router class: " . get_class($this->getRouter()));
        error_log("Application addRoute - Router instance ID: " . spl_object_id($this->getRouter()));

        $this->getRouter()->addRoute($method, $path, $handler);
        return $this;
    }

    /**
     * Bind a custom function to the container
     * 
     * @param string $name The function name
     * @param callable $function The function to bind
     * @return self
     */
    public function bindFunction(string $name, callable $function): self
    {
        $this->container->bindFunction($name, $function);
        return $this;
    }

    /**
     * Bind multiple functions to the container
     * 
     * @param array<string, callable> $functions Array of function name => callable pairs
     * @return self
     */
    public function bindFunctions(array $functions): self
    {
        foreach ($functions as $name => $function) {
            $this->container->bindFunction($name, $function);
        }
        return $this;
    }

    /**
     * Get a function from the container
     * 
     * @param string $name The function name
     * @return callable|null
     */
    public function getFunction(string $name): ?callable
    {
        return $this->container->getFunction($name);
    }

    /**
     * Get all bound functions
     * 
     * @return array<string, callable>
     */
    public function getFunctions(): array
    {
        return $this->container->getFunctions();
    }

    /**
     * Get the API server (alias for getApiServer)
     *
     * @return ApiServer
     */
    public function getApi(): ApiServer
    {
        return $this->getApiServer();
    }
}