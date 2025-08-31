<?php

namespace Gwack\Core\Exceptions;

use Exception;

/**
 * Validation Exception
 *
 * Thrown when request validation fails
 *
 * @package Gwack\Core\Exceptions
 */
class ValidationException extends Exception
{
    private array $errors;

    /**
     * ValidationException constructor
     *
     * @param string $message
     * @param array $errors
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct(string $message = "", array $errors = [], int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
