<?php

namespace Gwack\Router;

use Gwack\Router\Interfaces\RouterInterface;
use Gwack\Router\Interfaces\CacheInterface;
use Gwack\Router\Interfaces\RouteCollectionInterface;

/**
 * HTTP router for the Qwack framework
 *
 * note: this router doesn't use the custom http requests for performance reasons
 *
 * Features:
 * - Method filtering for routes
 * - Static route optimization with O(1) lookup
 * - FastRoute-inspired regex grouping for dynamic routes
 * - Pre-compiled regex patterns for maximum performance
 * - Intelligent route grouping by path segments
 * - Optimized regular expressions for parameter matching
 * - Route compilation caching
 *
 * @package Gwack\Router
 */
class Router implements RouterInterface
{
    /**
     * @var RouteCollectionInterface The route collection
     */
    private RouteCollectionInterface $routes;

    /**
     * @var array Compiled route data
     */
    private array $compiledRoutes = [];

    /**
     * @var bool Whether routes have been compiled
     */
    private bool $routesCompiled = false;

    /**
     * @var CacheInterface|null Caching implementation
     */
    private ?CacheInterface $cache = null;

    /**
     * @var array|null Cached route metadata
     */
    private ?array $cachedMetadata = null;

    /**
     * @var string The cache key for compiled routes
     */
    private string $cacheKey = 'routes_compiled';

    /**
     * Router constructor
     *
     * @param RouteCollectionInterface|null $routes Optional route collection
     * @param CacheInterface|null $cache Optional cache implementation
     */
    public function __construct(?RouteCollectionInterface $routes = null, ?CacheInterface $cache = null)
    {
        $this->routes = $routes ?? new RouteCollection();
        $this->cache = $cache;

        // Try to load route metadata from cache
        if ($this->cache !== null && $this->cache->has($this->cacheKey)) {
            $cachedData = $this->cache->get($this->cacheKey);
            if (is_array($cachedData) && $this->validateCachedData($cachedData)) {
                // Store cached metadata, but don't mark as compiled until routes are added
                $this->cachedMetadata = $cachedData;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addRoute(string $method, string $path, callable|array $handler, array $options = []): RouterInterface
    {
        $method = strtoupper($method);

        // Create a new route
        $route = new Route($method, $path, $handler, $options);

        // Add to collection
        $this->routes->add($route);

        // Mark as needing recompilation
        $this->routesCompiled = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('GET', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('POST', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('PUT', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('DELETE', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('PATCH', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function head(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('HEAD', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        return $this->addRoute('OPTIONS', $path, $handler, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $path, callable|array $handler, array $options = []): RouterInterface
    {
        // Add the same route for all HTTP methods
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $options);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(string $method, string $uri)
    {
        $result = $this->match($method, $uri);

        if ($result === null) {
            throw new \RuntimeException("No route matches $method $uri", 404);
        }

        [$handler, $params] = $result;

        // Call the handler with parameters
        if (is_callable($handler)) {
            return call_user_func_array($handler, array_values($params));
        }

        return $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoutes(): array
    {
        $routes = [];
        foreach ($this->routes->all() as $route) {
            $method = $route->getMethod();
            if (!isset($routes[$method])) {
                $routes[$method] = [];
            }
            $routes[$method][] = $route;
        }
        return $routes;
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $method, string $uri): ?array
    {
        $method = strtoupper($method);

        // remove trailing slashes (except for root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // Compile routes if necessary
        if (!$this->routesCompiled) {
            $this->compileRoutes();
        }

        // Handle HEAD requests by falling back to GET
        if ($method === 'HEAD') {
            $result = $this->matchRoutes('GET', $uri);
            if ($result !== null) {
                return $result;
            }
        }

        // Handle OPTIONS requests by returning allowed methods
        if ($method === 'OPTIONS') {
            $allowedMethods = $this->getAllowedMethods($uri);
            if (!empty($allowedMethods)) {
                return [
                    fn() => ['Allow' => implode(', ', $allowedMethods)],
                    []
                ];
            }
        }

        return $this->matchRoutes($method, $uri);
    }

    /**
     * Match routes for a specific method and URI
     *
     * @param string $method HTTP method
     * @param string $uri URI to match
     * @return array|null Match result
     */
    private function matchRoutes(string $method, string $uri): ?array
    {
        // Try static routes first (O(1) lookup)
        if (isset($this->compiledRoutes['static'][$method][$uri])) {
            $route = $this->compiledRoutes['static'][$method][$uri];
            return [$route->getHandler(), []];
        }

        // Try dynamic routes
        if (isset($this->compiledRoutes['dynamic'][$method])) {
            return $this->matchDynamicRoutes($method, $uri);
        }

        return null;
    }

    /**
     * Get allowed methods for a URI
     *
     * @param string $uri URI to check
     * @return array Allowed methods
     */
    public function getAllowedMethods(string $uri): array
    {
        // Normalize URI: remove trailing slashes (except for root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        $allowedMethods = [];

        foreach ($this->compiledRoutes['static'] as $method => $routes) {
            if (isset($routes[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        foreach ($this->compiledRoutes['dynamic'] as $method => $groups) {
            if ($this->matchDynamicRoutes($method, $uri) !== null) {
                $allowedMethods[] = $method;
            }
        }

        return array_unique($allowedMethods);
    }

    /**
     * Match against dynamic routes
     *
     * @param string $method HTTP method
     * @param string $uri URI to match
     * @return array|null Match result
     */
    private function matchDynamicRoutes(string $method, string $uri): ?array
    {
        $groups = $this->compiledRoutes['dynamic'][$method];

        // Extract first segment for group selection
        $segments = explode('/', trim($uri, '/'));
        $firstSegment = $segments[0] ?? '';

        // Try specific group first
        if (isset($groups[$firstSegment])) {
            $result = $this->matchRouteGroup($groups[$firstSegment], $uri);
            if ($result !== null) {
                return $result;
            }
        }

        // Try dynamic group
        if (isset($groups['__dynamic__'])) {
            $result = $this->matchRouteGroup($groups['__dynamic__'], $uri);
            if ($result !== null) {
                return $result;
            }
        }

        // Try all other groups as fallback
        foreach ($groups as $segment => $group) {
            if ($segment !== $firstSegment && $segment !== '__dynamic__') {
                $result = $this->matchRouteGroup($group, $uri);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Match against a specific route group
     *
     * @param array $group Route group data
     * @param string $uri URI to match
     * @return array|null Match result
     */
    private function matchRouteGroup(array $group, string $uri): ?array
    {
        if ($group['type'] === 'single') {
            // Single route - direct match
            $params = $group['route']->matches($uri);
            if ($params !== false) {
                return [$group['route']->getHandler(), $params];
            }
        } else {
            // Group regex
            if (preg_match($group['pattern'], $uri, $matches)) {
                // Find which route matched
                foreach ($group['routeMap'] as $index => $routeData) {
                    if (isset($matches["route$index"]) && $matches["route$index"] !== '') {
                        // This route matched - extract parameters
                        $params = $routeData['route']->matches($uri);
                        if ($params !== false) {
                            return [$routeData['route']->getHandler(), $params];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Compile all routes
     *
     * @return void
     */
    public function compileRoutes(): void
    {
        // If we have cached metadata and it matches our current routes, restore from cache
        if ($this->cachedMetadata !== null && $this->cacheIsValid()) {
            $this->restoreFromCache($this->cachedMetadata);
            $this->routesCompiled = true;
            return;
        }

        // Otherwise, compile from scratch
        $this->compiledRoutes = RouteCompiler::compile($this->routes->all());
        $this->routesCompiled = true;

        // Save metadata to cache if available (excluding handlers to avoid serialization issues)
        if ($this->cache !== null) {
            $cacheableData = $this->createCacheableData($this->compiledRoutes);
            $this->cache->set($this->cacheKey, $cacheableData);
            $this->cachedMetadata = $cacheableData;
        }
    }

    /**
     * Check if cached metadata is still valid for current routes
     *
     * @return bool
     */
    private function cacheIsValid(): bool
    {
        // Simple validation: check if route count matches
        $currentRoutes = $this->routes->all();
        $currentCount = count($currentRoutes);

        $cachedStaticCount = 0;
        $cachedDynamicCount = 0;

        foreach ($this->cachedMetadata['static'] as $routes) {
            $cachedStaticCount += count($routes);
        }

        foreach ($this->cachedMetadata['dynamic'] as $groups) {
            foreach ($groups as $group) {
                if ($group['type'] === 'single') {
                    $cachedDynamicCount += 1;
                } else {
                    $cachedDynamicCount += count($group['routeMap'] ?? []);
                }
            }
        }

        return $currentCount === ($cachedStaticCount + $cachedDynamicCount);
    }

    /**
     * Create cacheable version of compiled routes (without handlers)
     *
     * @param array $compiledRoutes
     * @return array
     */
    private function createCacheableData(array $compiledRoutes): array
    {
        $cacheable = [
            'static' => [],
            'dynamic' => []
        ];

        // Cache static routes metadata
        foreach ($compiledRoutes['static'] as $method => $routes) {
            $cacheable['static'][$method] = [];
            foreach ($routes as $path => $route) {
                $cacheable['static'][$method][$path] = [
                    'pattern' => $route->getPattern(),
                    'parameters' => $route->getParameterNames(),
                    'paramConstraints' => $route->getParameterConstraints()
                ];
            }
        }

        // Cache dynamic routes metadata
        foreach ($compiledRoutes['dynamic'] as $method => $groups) {
            $cacheable['dynamic'][$method] = [];
            foreach ($groups as $segment => $group) {
                $cacheable['dynamic'][$method][$segment] = [
                    'pattern' => $group['pattern'],
                    'type' => $group['type']
                ];

                if ($group['type'] === 'single') {
                    $route = $group['route'];
                    $cacheable['dynamic'][$method][$segment]['route'] = [
                        'pattern' => $route->getPattern(),
                        'parameters' => $route->getParameterNames(),
                        'paramConstraints' => $route->getParameterConstraints()
                    ];
                } else {
                    // Group type
                    $cacheable['dynamic'][$method][$segment]['routeMap'] = [];
                    foreach ($group['routeMap'] as $index => $routeData) {
                        $route = $routeData['route'];
                        $cacheable['dynamic'][$method][$segment]['routeMap'][$index] = [
                            'pattern' => $route->getPattern(),
                            'parameters' => $route->getParameterNames(),
                            'paramConstraints' => $route->getParameterConstraints()
                        ];
                    }
                }
            }
        }

        return $cacheable;
    }

    /**
     * Validate cached data structure
     *
     * @param array $cachedData
     * @return bool
     */
    private function validateCachedData(array $cachedData): bool
    {
        return isset($cachedData['static'], $cachedData['dynamic']) &&
            is_array($cachedData['static']) &&
            is_array($cachedData['dynamic']);
    }

    /**
     * Restore routes from cached metadata
     *
     * @param array $cachedData
     * @return void
     */
    private function restoreFromCache(array $cachedData): void
    {
        $this->compiledRoutes = [
            'static' => [],
            'dynamic' => []
        ];

        // Restore static routes
        foreach ($cachedData['static'] as $method => $routes) {
            $this->compiledRoutes['static'][$method] = [];
            foreach ($routes as $path => $routeData) {
                // Find the actual route from the collection
                $actualRoute = $this->findRouteInCollection($method, $path);
                if ($actualRoute !== null) {
                    $this->compiledRoutes['static'][$method][$path] = $actualRoute;
                }
            }
        }

        // Restore dynamic routes
        foreach ($cachedData['dynamic'] as $method => $groups) {
            $this->compiledRoutes['dynamic'][$method] = [];
            foreach ($groups as $segment => $group) {
                if ($group['type'] === 'single') {
                    // Single route
                    $actualRoute = $this->findRouteInCollection($method, $group['route']['pattern']);
                    if ($actualRoute !== null) {
                        $this->compiledRoutes['dynamic'][$method][$segment] = [
                            'type' => 'single',
                            'pattern' => $group['pattern'],
                            'route' => $actualRoute,
                            'params' => $group['route']['parameters']
                        ];
                    }
                } else {
                    // Group of routes
                    $routeMap = [];
                    foreach ($group['routeMap'] as $index => $routeData) {
                        // Find the actual route from the collection
                        $actualRoute = $this->findRouteInCollection($method, $routeData['pattern']);
                        if ($actualRoute !== null) {
                            $routeMap[$index] = [
                                'route' => $actualRoute,
                                'pattern' => $actualRoute->getCompiledPattern(),
                                'params' => $actualRoute->getParameterNames()
                            ];
                        }
                    }

                    if (!empty($routeMap)) {
                        $this->compiledRoutes['dynamic'][$method][$segment] = [
                            'type' => 'group',
                            'pattern' => $group['pattern'],
                            'routeMap' => $routeMap
                        ];
                    }
                }
            }
        }
    }

    /**
     * Find a route in the collection by method and pattern
     *
     * @param string $method
     * @param string $pattern
     * @return Route|null
     */
    private function findRouteInCollection(string $method, string $pattern): ?Route
    {
        foreach ($this->routes->all() as $route) {
            if ($route->getMethod() === $method && $route->getPattern() === $pattern) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Set a custom regex pattern for a parameter across all routes
     *
     * @param string $param Parameter name
     * @param string $pattern Regex pattern
     * @return self For method chaining
     */
    public function where(string $param, string $pattern): self
    {
        $this->routes->where($param, $pattern);
        $this->routesCompiled = false;
        return $this;
    }

    /**
     * Set multiple regex patterns at once
     *
     * @param array $patterns [param => regex]
     * @return self For method chaining
     */
    public function whereMultiple(array $patterns): self
    {
        $this->routes->whereMultiple($patterns);
        $this->routesCompiled = false;
        return $this;
    }

    /**
     * Get the route collection
     *
     * @return RouteCollectionInterface
     */
    public function getRouteCollection(): RouteCollectionInterface
    {
        return $this->routes;
    }

    /**
     * Add a named route
     *
     * @param string $method HTTP method
     * @param string $path URL path pattern
     * @param callable $handler Route handler
     * @param string $name Route name
     * @param array $options Additional options
     * @return self For method chaining
     */
    public function addNamedRoute(string $method, string $path, callable $handler, string $name, array $options = []): self
    {
        $this->routes->addNamed($name, $method, $path, $handler, $options);
        $this->routesCompiled = false;
        return $this;
    }

    /**
     * Get a named route
     *
     * @param string $name Route name
     * @return Route|null
     */
    public function getNamedRoute(string $name): ?Route
    {
        return $this->routes->getNamed($name);
    }

    /**
     * Check if a route name exists
     *
     * @param string $name Route name
     * @return bool
     */
    public function hasNamedRoute(string $name): bool
    {
        return $this->routes->hasNamed($name);
    }

    /**
     * Generate URL for a named route
     *
     * @param string $name Route name
     * @param array $params Parameters for the route
     * @return string Generated URL
     */
    public function url(string $name, array $params = []): string
    {
        return "URL generation for named routes is not implemented yet.";
    }
}
