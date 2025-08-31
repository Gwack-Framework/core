<?php

namespace Gwack\Api\Exceptions;

/**
 * Exception thrown when an HTTP method is not allowed
 *
 * @package Gwack\Api\Exceptions
 */
class MethodNotAllowedException extends ApiException
{
    /**
     * @var array Allowed methods
     */
    private array $allowedMethods;

    /**
     * Constructor
     *
     * @param array $allowedMethods List of allowed methods
     * @param string $message Error message
     * @param int $code Error code
     */
    public function __construct(array $allowedMethods = [], string $message = 'Method not allowed', int $code = 0)
    {
        parent::__construct($message, 405, ['allowed_methods' => $allowedMethods], $code);
        $this->allowedMethods = $allowedMethods;
    }

    /**
     * Get allowed methods
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
