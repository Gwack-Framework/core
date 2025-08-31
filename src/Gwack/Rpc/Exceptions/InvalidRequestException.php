<?php

namespace Gwack\Rpc\Exceptions;

/**
 * Exception thrown when the request format is invalid
 * 
 * @package Gwack\Rpc\Exceptions
 */
class InvalidRequestException extends RpcException
{
    public function __construct(string $message = 'Invalid request', ?\Throwable $previous = null)
    {
        parent::__construct(
            $message,
            -32600, // Invalid request
            null,
            0,
            $previous
        );
    }
}
