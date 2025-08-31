<?php

namespace Gwack\Rpc\Exceptions;

/**
 * Base RPC exception
 * 
 * @package Gwack\Rpc\Exceptions
 */
class RpcException extends \Exception
{
    /**
     * @var int The RPC error code
     */
    protected int $rpcCode;

    /**
     * @var mixed Additional error data
     */
    protected mixed $errorData;

    /**
     * Create a new RPC exception
     * 
     * @param string $message The error message
     * @param int $rpcCode The RPC-specific error code
     * @param mixed $errorData Additional error data
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message = '',
        int $rpcCode = -32603,
        mixed $errorData = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $rpcCode, $previous);
        $this->rpcCode = $rpcCode;
        $this->errorData = $errorData;
    }

    /**
     * Get the RPC error code
     * 
     * @return int
     */
    public function getRpcCode(): int
    {
        return $this->rpcCode;
    }

    /**
     * Get additional error data
     * 
     * @return mixed
     */
    public function getErrorData(): mixed
    {
        return $this->errorData;
    }
}
