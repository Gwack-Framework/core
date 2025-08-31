<?php

namespace Gwack\Router\Interfaces;

/**
 * Interface RouterInterface
 *
 * Defines the contract for our router implementation
 *
 * @package Gwack\RouterInterfaces
 */
interface RouterInterface
{
    /**
     * Add a route to the router
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function addRoute(string $method, string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a route that matches any HTTP method
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function any(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a GET route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function get(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a POST route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function post(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a PUT route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function put(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a DELETE route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function delete(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a PATCH route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function patch(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add a HEAD route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function head(string $path, callable|array $handler, array $options = []): self;

    /**
     * Add an OPTIONS route
     * 
     * @param string $path URL path pattern to match
     * @param callable|array $handler Callback to execute when route is matched
     * @param array $options Additional options for the route
     * @return self For method chaining
     */
    public function options(string $path, callable|array $handler, array $options = []): self;

    /**
     * Match a request against the registered routes
     * 
     * @param string $method HTTP method of the request
     * @param string $uri URI to match
     * @return array|null Returns [handler, params] if matched, null otherwise
     */
    public function match(string $method, string $uri): ?array;

    /**
     * Dispatch a request to the appropriate handler
     * 
     * @param string $method HTTP method of the request
     * @param string $uri URI to match
     * @return mixed The result of the handler
     * @throws \RuntimeException If no route matches
     */
    public function dispatch(string $method, string $uri);

    /**
     * Get all defined routes
     * 
     * @return array All routes organized by method
     */
    public function getRoutes(): array;

    /**
     * Compile all routes for maximum performance
     * 
     * @return void
     */
    public function compileRoutes(): void;
}
