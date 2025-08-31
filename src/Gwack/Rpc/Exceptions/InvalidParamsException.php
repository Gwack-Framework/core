<?php

namespace Gwack\Rpc\Exceptions;

/**
 * Exception thrown when invalid parameters are provided
 * 
 * @package Gwack\Rpc\Exceptions
 */
class InvalidParamsException extends RpcException
{
    public function __construct(string $message = 'Invalid parameters', ?\Throwable $previous = null)
    {
        parent::__construct(
            $message,
            -32602, // Invalid params
            null,
            0,
            $previous
        );
    }
}
