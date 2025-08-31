<?php

namespace Gwack\Router;

use Gwack\Router\Interfaces\RouteCollectionInterface;
use Gwack\Router\Interfaces\RouteInterface;

/**
 * Class RouteCollection
 * 
 * A collection of routes with support for named routes and custom regex constraints
 * 
 * @package Gwack\Router
 */
class RouteCollection implements RouteCollectionInterface
{
    /**
     * @var array<string, RouteInterface> Named routes
     */
    private array $namedRoutes = [];

    /**
     * @var array<RouteInterface> All routes
     */
    private array $routes = [];

    /**
     * @var array Routes organized by HTTP method
     */
    private array $methodRoutes = [];

    /**
     * @var array Static routes organized by method for O(1) lookup
     */
    private array $staticRoutes = [];

    /**
     * @var array<string, string> Custom regex patterns for parameters
     */
    private array $patterns = [];

    /**
     * @var array Routes that have had custom regexes applied and need recompilation 
     */
    private array $routesToRecompile = [];

    /**
     * @var bool Whether the collection has been compiled
     */
    private bool $compiled = false;

    /**
     * @var array Cache for compiled routes
     */
    private array $compiledRoutes = [];

    /**
     * {@inheritdoc}
     */
    public function add(RouteInterface $route, ?string $name = null): RouteCollectionInterface
    {
        $this->routes[] = $route;

        // Index by method
        $method = $route->getMethod();
        if (!isset($this->methodRoutes[$method])) {
            $this->methodRoutes[$method] = [];
        }
        $this->methodRoutes[$method][] = $route;

        // Add to static routes if applicable
        if ($route instanceof Route && $route->isStatic()) {
            if (!isset($this->staticRoutes[$method])) {
                $this->staticRoutes[$method] = [];
            }
            $this->staticRoutes[$method][$route->getPath()] = $route;
        }

        // Add as named route if a name is provided
        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }

        // Apply any global patterns to the route
        if (!empty($this->patterns) && $route instanceof Route) {
            $this->applyPatternsToRoute($route);
        }

        $this->compiled = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): ?RouteInterface
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * {@inheritdoc}
     */
    public function getByMethod(string $method): array
    {
        return $this->methodRoutes[$method] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function where(string $param, string $regex): RouteCollectionInterface
    {
        $this->patterns[$param] = $regex;

        // Apply this pattern to all existing routes
        foreach ($this->routes as $route) {
            if ($route instanceof Route) {
                $this->applyPatternToRoute($route, $param, $regex);
            }
        }

        $this->compiled = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function whereMultiple(array $patterns): RouteCollectionInterface
    {
        foreach ($patterns as $param => $regex) {
            $this->where($param, $regex);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getStaticRoutes(): array
    {
        return $this->staticRoutes;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(): void
    {
        // Only recompile routes that need it
        foreach ($this->routesToRecompile as $route) {
            $route->compile();
        }

        // Clear the recompile list
        $this->routesToRecompile = [];

        // Mark as compiled
        $this->compiled = true;
    }

    /**
     * Get a route by path and method
     * 
     * @param string $path The route path
     * @param string $method The HTTP method
     * @return RouteInterface|null
     */
    public function getByPathAndMethod(string $path, string $method): ?RouteInterface
    {
        // Check static routes first for O(1) lookup
        if (isset($this->staticRoutes[$method][$path])) {
            return $this->staticRoutes[$method][$path];
        }

        // Otherwise, check method routes
        $routes = $this->methodRoutes[$method] ?? [];
        foreach ($routes as $route) {
            if ($route->matches($path) !== false) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Match a path and method against all routes
     * 
     * @param string $method HTTP method
     * @param string $path URI path
     * @return array|null [route, params] or null if no match
     */
    public function match(string $method, string $path): ?array
    {
        if (!$this->compiled) {
            $this->compile();
        }

        // First check static routes for O(1) lookup
        if (isset($this->staticRoutes[$method][$path])) {
            return [$this->staticRoutes[$method][$path], []];
        }

        // Check dynamic routes
        $methodRoutes = $this->methodRoutes[$method] ?? [];

        foreach ($methodRoutes as $route) {
            $params = $route->matches($path);
            if ($params !== false) {
                return [$route, $params];
            }
        }

        return null;
    }

    /**
     * Apply custom regex pattern to a specific route
     * 
     * @param Route $route Route to apply pattern to
     * @param string $param Parameter name
     * @param string $regex Regular expression pattern
     * @return void
     */
    private function applyPatternToRoute(Route $route, string $param, string $regex): void
    {
        // Skip if this route doesn't have this parameter
        if (!in_array($param, $route->getParameterNames())) {
            return;
        }

        // Set custom regex on the route
        $route->setParameterPattern($param, $regex);

        // Mark for recompilation
        $this->routesToRecompile[$route->getPath()] = $route;
        $this->compiled = false;
    }

    /**
     * Apply all patterns to a route
     * 
     * @param Route $route Route to apply patterns to
     * @return void
     */
    private function applyPatternsToRoute(Route $route): void
    {
        $paramNames = $route->getParameterNames();
        foreach ($paramNames as $paramName) {
            if (isset($this->patterns[$paramName])) {
                $route->setParameterPattern($paramName, $this->patterns[$paramName]);
                $this->routesToRecompile[$route->getPath()] = $route;
            }
        }
    }

    /**
     * Cache routes compilation
     * 
     * @param array $cacheData Data to cache
     * @return bool Success or failure
     */
    public function cacheCompilation(array $cacheData): bool
    {
        $this->compiledRoutes = $cacheData;
        return true;
    }

    /**
     * Get compilation cache if available
     * 
     * @return array|null Cached compilation data or null if none
     */
    public function getCompilationCache(): ?array
    {
        return !empty($this->compiledRoutes) ? $this->compiledRoutes : null;
    }

    /**
     * Add a named route
     * 
     * @param string $name Route name
     * @param string $method HTTP method
     * @param string $path URL path pattern
     * @param callable $handler Route handler
     * @param array $options Additional options
     * @return RouteCollectionInterface For method chaining
     */
    public function addNamed(string $name, string $method, string $path, callable $handler, array $options = []): RouteCollectionInterface
    {
        $route = new Route($method, $path, $handler, $options);
        $this->add($route, $name);
        return $this;
    }

    /**
     * Get a named route
     * 
     * @param string $name Route name
     * @return Route|null
     */
    public function getNamed(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Check if a named route exists
     * 
     * @param string $name Route name
     * @return bool
     */
    public function hasNamed(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }
}
