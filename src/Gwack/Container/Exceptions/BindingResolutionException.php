<?php

namespace Gwack\Container\Exceptions;

/**
 * Exception thrown when a binding cannot be resolved
 *
 * @package Gwack\Container\Exceptions
 */
class BindingResolutionException extends ContainerException
{
    /**
     * Create a new BindingResolutionException
     *
     * @param string $abstract The service identifier that couldn't be resolved
     * @param string $reason The reason for the failure
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(string $abstract, string $reason = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Unable to resolve binding for '{$abstract}'";
        if ($reason) {
            $message .= ": {$reason}";
        }
        parent::__construct($message, $code, $previous);
    }
}
