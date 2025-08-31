<?php

namespace Gwack\Api\Exceptions;

/**
 * Exception thrown when validation fails
 *
 * @package Gwack\Api\Exceptions
 */
class ValidationException extends ApiException
{
    /**
     * @var array Validation errors
     */
    private array $errors;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param array $errors Validation errors
     * @param int $code Error code
     */
    public function __construct(string $message = 'Validation failed', array $errors = [], int $code = 0)
    {
        parent::__construct($message, 422, ['validation_errors' => $errors], $code);
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
