<?php

namespace Gwack\Rpc\Interfaces;

use Gwack\Rpc\Exceptions\RpcException;

/**
 * Interface for RPC method handlers
 * 
 * Defines the contract for services that can handle RPC method calls
 * with proper parameter resolution and result handling.
 * 
 * @package Gwack\Rpc\Interfaces
 */
interface RpcHandlerInterface
{
    /**
     * Handle an RPC method call
     * 
     * @param string $method The method name to call
     * @param array $parameters The method parameters
     * @return mixed The method result
     * @throws RpcException If the method call fails
     */
    public function handleCall(string $method, array $parameters = []): mixed;

    /**
     * Get available methods for this handler
     * 
     * @return array Array of method names
     */
    public function getMethods(): array;

    /**
     * Check if a method is available
     * 
     * @param string $method The method name
     * @return bool
     */
    public function hasMethod(string $method): bool;

    /**
     * Get method metadata (parameters, return type, etc.)
     * 
     * @param string $method The method name
     * @return array Method metadata
     */
    public function getMethodMetadata(string $method): array;
}
