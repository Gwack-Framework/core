<?php

namespace Gwack\Api\Interfaces;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for API resource validation
 *
 * Validates request data and parameters according to defined rules
 *
 * @package Gwack\Api\Interfaces
 */
interface ValidatorInterface
{
    /**
     * Validate request data
     *
     * @param Request $request The HTTP request
     * @param array $rules Validation rules
     * @return ValidationResult The validation result
     */
    public function validate(Request $request, array $rules): ValidationResult;

    /**
     * Validate specific data against rules
     *
     * @param array $data The data to validate
     * @param array $rules Validation rules
     * @return ValidationResult The validation result
     */
    public function validateData(array $data, array $rules): ValidationResult;
}

/**
 * Validation result container
 */
class ValidationResult
{
    public function __construct(
        private bool $isValid,
        private array $errors = [],
        private array $validatedData = []
    ) {
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidatedData(): array
    {
        return $this->validatedData;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
