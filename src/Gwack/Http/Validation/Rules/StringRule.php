<?php

namespace Gwack\Http\Validation\Rules;

use Gwack\Http\Validation\Rule;
use Gwack\Http\Request;

/**
 * String validation rule
 * 
 * Validates that a field is a string and optionally enforces length constraints.
 * 
 * @package Gwack\Http\Validation\Rules
 */
class StringRule extends Rule
{
    /**
     * Execute the validation rule on a specific field in the request
     *
     * @param string $field The field to validate
     * @param Request $request The request containing the data
     * @return mixed The result of the validation, true or an error message
     */
    public function executeOnRequest(string $field, Request $request): mixed
    {
        $data = $request->getAllData();
        $value = $data[$field] ?? null;
        $parameters = $this->getParameters();

        // Allow null values if not required
        if ($value === null) {
            return true;
        }

        // Check if value is a string
        if (!is_string($value) && !is_scalar($value)) {
            return "The {$field} field must be a string.";
        }

        // Convert to string if scalar
        if (is_scalar($value)) {
            $value = (string) $value;
        }

        // Check minimum length if specified
        if (isset($parameters['min']) && strlen($value) < (int) $parameters['min']) {
            return "The {$field} field must be at least {$parameters['min']} characters.";
        }

        // Check maximum length if specified
        if (isset($parameters['max']) && strlen($value) > (int) $parameters['max']) {
            return "The {$field} field must not exceed {$parameters['max']} characters.";
        }

        // Check exact length if specified
        if (isset($parameters['length']) && strlen($value) !== (int) $parameters['length']) {
            return "The {$field} field must be exactly {$parameters['length']} characters.";
        }

        // Check if it matches a specific pattern
        if (isset($parameters['pattern'])) {
            if (!preg_match($parameters['pattern'], $value)) {
                return "The {$field} field format is invalid.";
            }
        }

        return true;
    }

    /**
     * Validate string length constraints
     *
     * @param string $value The value to validate
     * @param array $constraints Length constraints (min, max, length)
     * @return bool|string True if valid, error message if invalid
     */
    private function validateLength(string $value, array $constraints): bool|string
    {
        $length = strlen($value);

        if (isset($constraints['min']) && $length < $constraints['min']) {
            return "String must be at least {$constraints['min']} characters long.";
        }

        if (isset($constraints['max']) && $length > $constraints['max']) {
            return "String must not exceed {$constraints['max']} characters.";
        }

        if (isset($constraints['length']) && $length !== $constraints['length']) {
            return "String must be exactly {$constraints['length']} characters long.";
        }

        return true;
    }
}
