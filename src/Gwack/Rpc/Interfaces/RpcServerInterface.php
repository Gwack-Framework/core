<?php

namespace Gwack\Rpc\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for RPC server implementations
 * 
 * Defines the contract for handling RPC requests with high performance
 * and support for various serialization formats.
 * 
 * @package Gwack\Rpc\Interfaces
 */
interface RpcServerInterface
{
    /**
     * Handle an RPC request
     * 
     * @param Request $request The HTTP request containing the RPC call
     * @return Response The HTTP response with the result
     */
    public function handleRequest(Request $request): Response;

    /**
     * Register a service for RPC calls
     * 
     * @param string $name The service name
     * @param object|string $service The service instance or class name
     * @return void
     */
    public function registerService(string $name, object|string $service): void;

    /**
     * Register multiple services at once
     * 
     * @param array $services Array of service name => service mappings
     * @return void
     */
    public function registerServices(array $services): void;

    /**
     * Check if a service is registered
     * 
     * @param string $name The service name
     * @return bool
     */
    public function hasService(string $name): bool;

    /**
     * Get registered service names
     * 
     * @return array
     */
    public function getServiceNames(): array;

    /**
     * Set the serializer for request/response handling
     * 
     * @param SerializerInterface $serializer
     * @return void
     */
    public function setSerializer(SerializerInterface $serializer): void;

    /**
     * Add middleware for request/response processing
     * 
     * @param MiddlewareInterface $middleware
     * @return void
     */
    public function addMiddleware(MiddlewareInterface $middleware): void;
}
