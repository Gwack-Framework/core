<?php

namespace Gwack\Rpc\Exceptions;

/**
 * Exception thrown when a method is not found
 * 
 * @package Gwack\Rpc\Exceptions
 */
class MethodNotFoundException extends RpcException
{
    public function __construct(string $method, ?\Throwable $previous = null)
    {
        parent::__construct(
            "Method '{$method}' not found",
            -32601, // Method not found
            null,
            0,
            $previous
        );
    }
}
