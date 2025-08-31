<?php

namespace Gwack\Api\Exceptions;

/**
 * Exception thrown when a resource is not found
 *
 * @package Gwack\Api\Exceptions
 */
class NotFoundException extends ApiException
{
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     */
    public function __construct(string $message = 'Resource not found', int $code = 0)
    {
        parent::__construct($message, 404, [], $code);
    }
}
