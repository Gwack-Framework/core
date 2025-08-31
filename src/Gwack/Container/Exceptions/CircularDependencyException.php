<?php

namespace Gwack\Container\Exceptions;

/**
 * Exception thrown when there's a circular dependency in service resolution
 *
 * @package Gwack\Container\Exceptions
 */
class CircularDependencyException extends ContainerException
{
    /**
     * Create a new CircularDependencyException
     *
     * @param string $service The service that caused the circular dependency
     * @param array $stack The dependency stack
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(string $service, array $stack = [], int $code = 0, ?\Throwable $previous = null)
    {
        $stackString = implode(' -> ', $stack);
        $message = "Circular dependency detected for service '{$service}'. Stack: {$stackString}";
        parent::__construct($message, $code, $previous);
    }
}
