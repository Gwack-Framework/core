<?php

namespace Gwack\Router\Interfaces;

/**
 * Interface RouteCollectionInterface
 * 
 * Defines the contract for a collection of routes in the routing system
 * 
 * @package Gwack\RouterInterfaces
 */
interface RouteCollectionInterface
{
    /**
     * Add a route to the collection
     * 
     * @param RouteInterface $route The route to add
     * @param string|null $name Optional name for the route
     * @return self For method chaining
     */
    public function add(RouteInterface $route, ?string $name = null): self;

    /**
     * Get a route by name
     * 
     * @param string $name The name of the route
     * @return RouteInterface|null The route if found, null otherwise
     */
    public function get(string $name): ?RouteInterface;

    /**
     * Get all routes in the collection
     * 
     * @return array<RouteInterface> Array of all routes
     */
    public function all(): array;

    /**
     * Get routes filtered by HTTP method
     * 
     * @param string $method HTTP method to filter by
     * @return array<RouteInterface> Array of matching routes
     */
    public function getByMethod(string $method): array;

    /**
     * Set custom regex patterns for route parameters
     * 
     * @param string $param Parameter name
     * @param string $regex Regular expression pattern without delimiters
     * @return self For method chaining
     */
    public function where(string $param, string $regex): self;

    /**
     * Set multiple custom regex patterns at once
     * 
     * @param array<string, string> $patterns Array of patterns [param => regex]
     * @return self For method chaining
     */
    public function whereMultiple(array $patterns): self;

    /**
     * Checks if a named route exists
     * 
     * @param string $name Route name
     * @return bool True if the route exists
     */
    public function has(string $name): bool;

    /**
     * Get static routes organized by method for O(1) lookup
     * 
     * @return array Static routes [method => [path => route]]
     */
    public function getStaticRoutes(): array;

    /**
     * Compile all routes in the collection for optimized matching
     * 
     * @return void
     */
    public function compile(): void;
}
