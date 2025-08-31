<?php

namespace Gwack\Container\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when a service is not found in the container
 *
 * @package Gwack\Container\Exceptions
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    /**
     * Create a new NotFoundException
     *
     * @param string $id The service identifier that was not found
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous exception
     */
    public function __construct(string $id, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Service '{$id}' not found in container", $code, $previous);
    }
}
