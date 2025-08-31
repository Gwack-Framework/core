<?php

namespace Gwack\Http\Validation\Rules;

use Gwack\Http\Validation\Rule;
use Gwack\Http\Request;

/**
 * Number validation rule
 * 
 * Validates that a field is a number (integer or float) and optionally enforces range constraints.
 * 
 * @package Gwack\Http\Validation\Rules
 */
class NumberRule extends Rule
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

        // Check if value is numeric
        if (!is_numeric($value)) {
            return "The {$field} field must be a number.";
        }

        // Convert to appropriate number type
        $numericValue = $this->parseNumericValue($value, $parameters);

        if ($numericValue === false) {
            return "The {$field} field must be a valid number.";
        }

        // Validate range constraints
        $rangeValidation = $this->validateRange($numericValue, $parameters, $field);
        if ($rangeValidation !== true) {
            return $rangeValidation;
        }

        // Validate decimal places if specified
        if (isset($parameters['decimals'])) {
            $decimalsValidation = $this->validateDecimals($value, $parameters['decimals'], $field);
            if ($decimalsValidation !== true) {
                return $decimalsValidation;
            }
        }

        // Validate integer constraint if specified
        if (isset($parameters['integer']) && $parameters['integer']) {
            if (!$this->isInteger($value)) {
                return "The {$field} field must be an integer.";
            }
        }

        // Validate positive constraint if specified
        if (isset($parameters['positive']) && $parameters['positive']) {
            if ($numericValue <= 0) {
                return "The {$field} field must be a positive number.";
            }
        }

        // Validate negative constraint if specified
        if (isset($parameters['negative']) && $parameters['negative']) {
            if ($numericValue >= 0) {
                return "The {$field} field must be a negative number.";
            }
        }

        return true;
    }

    /**
     * Parse and convert value to appropriate numeric type
     *
     * @param mixed $value The value to parse
     * @param array $parameters Validation parameters
     * @return int|float|false Parsed number or false if invalid
     */
    private function parseNumericValue(mixed $value, array $parameters): int|float|false
    {
        // For general numeric values
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            // Remove whitespace
            $value = trim($value);

            // Check for valid numeric string
            if (is_numeric($value)) {
                // Return as float to preserve decimal values
                return (float) $value;
            }
        }

        return false;
    }

    /**
     * Validate range constraints (min, max, between)
     *
     * @param int|float $value The numeric value
     * @param array $parameters Validation parameters
     * @param string $field Field name for error messages
     * @return bool|string True if valid, error message if invalid
     */
    private function validateRange(int|float $value, array $parameters, string $field): bool|string
    {
        // Check minimum value
        if (isset($parameters['min'])) {
            $min = (float) $parameters['min'];
            if ($value < $min) {
                return "The {$field} field must be at least {$min}.";
            }
        }

        // Check maximum value
        if (isset($parameters['max'])) {
            $max = (float) $parameters['max'];
            if ($value > $max) {
                return "The {$field} field must not exceed {$max}.";
            }
        }

        // Check between range
        if (isset($parameters['between']) && is_array($parameters['between']) && count($parameters['between']) === 2) {
            $min = (float) $parameters['between'][0];
            $max = (float) $parameters['between'][1];

            if ($value < $min || $value > $max) {
                return "The {$field} field must be between {$min} and {$max}.";
            }
        }

        return true;
    }

    /**
     * Validate decimal places constraint
     *
     * @param mixed $value The original value
     * @param int $maxDecimals Maximum allowed decimal places
     * @param string $field Field name for error messages
     * @return bool|string True if valid, error message if invalid
     */
    private function validateDecimals(mixed $value, int $maxDecimals, string $field): bool|string
    {
        $stringValue = (string) $value;

        // Find decimal point
        $decimalPos = strpos($stringValue, '.');

        if ($decimalPos === false) {
            // No decimal point, so 0 decimal places
            return true;
        }

        // Count decimal places
        $decimalPlaces = strlen($stringValue) - $decimalPos - 1;

        if ($decimalPlaces > $maxDecimals) {
            return "The {$field} field must not have more than {$maxDecimals} decimal places.";
        }

        return true;
    }

    /**
     * Check if value is an integer
     *
     * @param mixed $value The value to check
     * @return bool True if integer, false otherwise
     */
    private function isInteger(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return $value == (int) $value;
        }

        if (is_string($value)) {
            return ctype_digit(ltrim(trim($value), '-'));
        }

        return false;
    }

    /**
     * Validate specific number types
     *
     * @param mixed $value The value to validate
     * @param string $type The type to validate (int, float, decimal)
     * @return bool True if valid, false otherwise
     */
    private function validateNumberType(mixed $value, string $type): bool
    {
        return match ($type) {
            'int', 'integer' => $this->isInteger($value),
            'float', 'double' => is_float($value) || (is_string($value) && is_numeric($value) && str_contains($value, '.')),
            'decimal' => is_numeric($value),
            default => is_numeric($value)
        };
    }

    /**
     * Check if number is within a specific precision
     *
     * @param float $value The number to check
     * @param int $precision Maximum number of significant digits
     * @return bool True if within precision, false otherwise
     */
    private function validatePrecision(float $value, int $precision): bool
    {
        $stringValue = rtrim(rtrim(sprintf('%.15f', $value), '0'), '.');
        $significantDigits = strlen(str_replace(['.', '-'], '', $stringValue));

        return $significantDigits <= $precision;
    }
}
