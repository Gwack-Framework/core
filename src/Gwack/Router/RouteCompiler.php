<?php

namespace Gwack\Router;

/**
 * Route compiler that generates optimized regex patterns
 * Inspired by FastRoute's approach but with additional optimizations
 */
class RouteCompiler
{
    /**
     * Compile a set of routes into optimized regex groups
     * 
     * @param array $routes Array of Route objects
     * @param bool $forCache Whether to create a cache-friendly version (without handlers)
     * @return array Compiled route data
     */
    public static function compile(array $routes, bool $forCache = false): array
    {
        $compiledRoutes = [
            'static' => [],
            'dynamic' => []
        ];

        foreach ($routes as $route) {
            if ($route->isStatic()) {
                // Static routes go into a hash map for O(1) lookup
                $method = $route->getMethod();
                if (!isset($compiledRoutes['static'][$method])) {
                    $compiledRoutes['static'][$method] = [];
                }
                $compiledRoutes['static'][$method][$route->getPath()] = $route;
            } else {
                // Dynamic routes get compiled into regex groups
                $method = $route->getMethod();
                if (!isset($compiledRoutes['dynamic'][$method])) {
                    $compiledRoutes['dynamic'][$method] = [];
                }
                $compiledRoutes['dynamic'][$method][] = [
                    'route' => $route,
                    'pattern' => $route->getCompiledPattern(),
                    'params' => $route->getParameterNames()
                ];
            }
        }

        // Optimize dynamic routes by grouping them into efficient regex patterns
        foreach ($compiledRoutes['dynamic'] as $method => &$routes) {
            $routes = self::optimizeDynamicRoutes($routes);
        }

        return $compiledRoutes;
    }

    /**
     * Optimize dynamic routes by creating efficient regex groups
     * 
     * @param array $routes Dynamic routes for a specific method
     * @return array Optimized route groups
     */
    private static function optimizeDynamicRoutes(array $routes): array
    {
        // Group routes by their first static segment to reduce regex complexity
        $groups = [];

        foreach ($routes as $routeData) {
            $route = $routeData['route'];
            $path = $route->getPath();

            // Extract first segment
            $segments = explode('/', trim($path, '/'));
            $firstSegment = $segments[0] ?? '';

            // If first segment is dynamic, put in default group
            if (strpos($firstSegment, '{') !== false) {
                $firstSegment = '__dynamic__';
            }

            if (!isset($groups[$firstSegment])) {
                $groups[$firstSegment] = [];
            }

            $groups[$firstSegment][] = $routeData;
        }

        // Compile each group into an optimized regex
        $compiledGroups = [];
        foreach ($groups as $segment => $groupRoutes) {
            $compiledGroups[$segment] = self::compileRouteGroup($groupRoutes);
        }

        return $compiledGroups;
    }

    /**
     * Compile a group of routes into a single optimized regex
     * 
     * @param array $routes Routes to compile
     * @return array Compiled group data
     */
    private static function compileRouteGroup(array $routes): array
    {
        if (count($routes) === 1) {
            // Single route - use its pattern directly
            $routeData = $routes[0];
            return [
                'type' => 'single',
                'pattern' => $routeData['pattern'],
                'route' => $routeData['route'],
                'params' => $routeData['params']
            ];
        }

        // Multiple routes - create a combined regex with named groups
        $patterns = [];
        $routeMap = [];

        foreach ($routes as $index => $routeData) {
            $pattern = $routeData['pattern'];
            // Remove the anchors and delimiters
            $pattern = trim($pattern, '#^$');
            $patterns[] = "(?P<route$index>$pattern)";
            $routeMap[$index] = $routeData;
        }

        $combinedPattern = '#^(?:' . implode('|', $patterns) . ')$#';

        return [
            'type' => 'group',
            'pattern' => $combinedPattern,
            'routeMap' => $routeMap
        ];
    }
}
