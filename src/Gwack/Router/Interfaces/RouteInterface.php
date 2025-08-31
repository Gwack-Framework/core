<?php

namespace Gwack\Router\Interfaces;

/**
 * Interface RouteInterface
 * 
 * Defines the contract for a route in the routing system
 * 
 * @package Gwack\RouterInterfaces
 */
interface RouteInterface
{
    /**
     * Get the HTTP method for this route
     * 
     * @return string HTTP method (GET, POST, etc.)
     */
    public function getMethod(): string;

    /**
     * Get the path pattern for this route
     * 
     * @return string The path pattern
     */
    public function getPath(): string;

    /**
     * Get the handler for this route
     * 
     * @return callable|array The route handler
     */
    public function getHandler(): callable|array;

    /**
     * Check if this route matches the given path
     * 
     * @param string $path The path to check against
     * @return array|false Returns extracted parameters if matched, false otherwise
     */
    public function matches(string $path): array|false;

    /**
     * Get the compiled regex pattern for this route
     * 
     * @return string The compiled regex pattern
     */
    public function getCompiledPattern(): string;

    /**
     * Get the parameter names for this route
     * 
     * @return array List of parameter names
     */
    public function getParameterNames(): array;

    /**
     * Set a custom regex pattern for a route parameter
     * 
     * @param string $param Parameter name
     * @param string $pattern Regex pattern without delimiters
     * @return self For method chaining
     */
    public function where(string $param, string $pattern): self;

    /**
     * Set multiple patterns at once
     * 
     * @param array $patterns [param => regex]
     * @return self For method chaining
     */
    public function whereMultiple(array $patterns): self;

    /**
     * Compile this route for fast matching
     * 
     * @return void
     */
    public function compile(): void;
}
