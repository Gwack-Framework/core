<?php

namespace Gwack\Core\Routing;

use Gwack\Container\Container;
use Gwack\Core\Context;
use Gwack\Http\Request;
use Gwack\Core\Routing\RouteExecutor;
use Gwack\Api\ApiServer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Exception;

/**
 * File-Based Router
 *
 * Discovers and registers routes from the filesystem using our container system
 * This eliminates the need for route compilation while maintaining performance
 */
class FileBasedRouter
{
    private Container $container;
    private ApiServer $apiServer;
    private RouteExecutor $executor;
    private array $discoveredRoutes = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->apiServer = $container->get('api');
        $this->executor = new RouteExecutor($container);
    }

    /**
     * Discover and register routes from a directory
     *
     * @param string $directory Server directory containing route files
     * @return void
     */
    public function discoverRoutes(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($this->isRouteFile($file)) {
                $this->registerRouteFile($file, $directory);
            }
        }
    }

    /**
     * Check if a file is a valid route file
     *
     * @param SplFileInfo $file
     * @return bool
     */
    private function isRouteFile(SplFileInfo $file): bool
    {
        return $file->isFile() && $file->getExtension() === 'php';
    }

    /**
     * Register a route file with the router
     *
     * @param SplFileInfo $file
     * @param string $baseDirectory
     * @return void
     */
    private function registerRouteFile(SplFileInfo $file, string $baseDirectory): void
    {
        $relativePath = $this->getRelativePath($file->getPathname(), $baseDirectory);
        $routePath = $this->convertFilePathToRoute($relativePath);
        $methods = $this->determineHttpMethods($file);

        // Create a container resolver for this route
        $routeKey = "route.{$routePath}";

        $this->container->bind($routeKey, function () use ($file) {
            return $this->createRouteHandler($file);
        });

        // Register with the API server
        $handler = function () use ($routeKey) {
            $routeHandler = $this->container->get($routeKey);
            $context = $this->container->get('context');
            $request = $this->container->get(Request::class);

            return $routeHandler($context, $request);
        };

        $router = $this->apiServer->getRouter();


        foreach ($methods as $method) {
            // BYPASS API server and register directly with router to avoid path manipulation
            try {
                $router = $this->apiServer->getRouter();
                $router->addRoute($method, $routePath, $handler);
            } catch (Exception $e) {
                error_log("Error registering route {$method} {$routePath}: " . $e->getMessage());
            }
        }

        $this->discoveredRoutes[] = [
            'file' => $file->getPathname(),
            'route' => $routePath,
            'methods' => $methods
        ];
    }

    /**
     * Create a route handler from a file
     *
     * @param SplFileInfo $file
     * @return callable
     */
    private function createRouteHandler(SplFileInfo $file): callable
    {
        return function (Context $context, Request $request) use ($file) {
            return $this->executor->execute($file->getPathname(), $context, $request);
        };
    }

    /**
     * Convert file path to route path
     *
     * @param string $filePath
     * @return string
     */
    private function convertFilePathToRoute(string $filePath): string
    {
        // Remove .php extension
        $path = substr($filePath, 0, -4);

        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Handle index files
        if (basename($path) === 'index') {
            $path = dirname($path);
            if ($path === '.') {
                $path = '';
            }
        }

        // Handle dynamic routes [id] -> :id
        $path = preg_replace('/\[([^\]]+)\]/', ':$1', $path);

        // Ensure leading slash
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Handle root case
        if ($path === '/.') {
            $path = '/';
        }

        return $path;
    }

    /**
     * Determine HTTP methods for a route file
     *
     * @param SplFileInfo $file
     * @return array
     */
    private function determineHttpMethods(SplFileInfo $file): array
    {
        $filename = $file->getBasename('.php');

        // Check for method-specific files
        $methodMap = [
            'get' => ['GET'],
            'post' => ['POST'],
            'put' => ['PUT'],
            'patch' => ['PATCH'],
            'delete' => ['DELETE'],
            'options' => ['OPTIONS'],
            'head' => ['HEAD'],
        ];

        if (isset($methodMap[$filename])) {
            return $methodMap[$filename];
        }

        // Default to GET for index and named routes
        return ['GET'];
    }

    /**
     * Get relative path from base directory
     *
     * @param string $filePath
     * @param string $baseDirectory
     * @return string
     */
    private function getRelativePath(string $filePath, string $baseDirectory): string
    {
        $realFile = realpath($filePath);
        $realBase = realpath($baseDirectory);

        return substr($realFile, strlen($realBase) + 1);
    }

    /**
     * Get all discovered routes
     *
     * @return array
     */
    public function getDiscoveredRoutes(): array
    {
        return $this->discoveredRoutes;
    }
}
