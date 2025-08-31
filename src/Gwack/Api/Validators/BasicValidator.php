<?php

namespace Gwack\Api\Validators;

use Gwack\Api\Interfaces\ValidatorInterface;
use Gwack\Api\Interfaces\ValidationResult;
use Symfony\Component\HttpFoundation\Request;

/**
 * Basic validator for API requests
 *
 * Provides common validation rules for API endpoints
 *
 * @package Gwack\Api\Validators
 */
class BasicValidator implements ValidatorInterface
{
    /**
     * Validate request data
     *
     * @param Request $request The HTTP request
     * @param array $rules Validation rules
     * @return ValidationResult The validation result
     */
    public function validate(Request $request, array $rules): ValidationResult
    {
        $data = $this->extractRequestData($request);
        return $this->validateData($data, $rules);
    }

    /**
     * Validate specific data against rules
     *
     * @param array $data The data to validate
     * @param array $rules Validation rules
     * @return ValidationResult The validation result
     */
    public function validateData(array $data, array $rules): ValidationResult
    {
        $errors = [];
        $validatedData = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $fieldErrors = $this->validateField($field, $value, $fieldRules, $data);

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors[0]; // Return first error for each field
            } else {
                $validatedData[$field] = $this->sanitizeValue($value, $fieldRules);
            }
        }

        return new ValidationResult(
            empty($errors),
            $errors,
            $validatedData
        );
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array|string $rules Validation rules
     * @param array $allData All data for context
     * @return array Validation errors
     */
    private function validateField(string $field, mixed $value, array|string $rules, array $allData): array
    {
        $errors = [];
        $ruleList = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($ruleList as $rule) {
            if (is_string($rule)) {
                $rule = $this->parseRule($rule);
            }

            $error = $this->applyRule($field, $value, $rule, $allData);
            if ($error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Parse a string rule into components
     *
     * @param string $rule Rule string
     * @return array Parsed rule
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            [$name, $params] = explode(':', $rule, 2);
            return [
                'name' => $name,
                'params' => explode(',', $params),
            ];
        }

        return ['name' => $rule, 'params' => []];
    }

    /**
     * Apply a validation rule
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $rule Rule definition
     * @param array $allData All data for context
     * @return string|null Error message or null if valid
     */
    private function applyRule(string $field, mixed $value, array $rule, array $allData): ?string
    {
        $ruleName = $rule['name'];
        $params = $rule['params'] ?? [];

        return match ($ruleName) {
            'required' => $this->validateRequired($field, $value),
            'optional' => null, // Always valid
            'string' => $this->validateString($field, $value),
            'integer', 'int' => $this->validateInteger($field, $value),
            'numeric' => $this->validateNumeric($field, $value),
            'boolean', 'bool' => $this->validateBoolean($field, $value),
            'email' => $this->validateEmail($field, $value),
            'url' => $this->validateUrl($field, $value),
            'array' => $this->validateArray($field, $value),
            'min' => $this->validateMin($field, $value, $params[0] ?? 0),
            'max' => $this->validateMax($field, $value, $params[0] ?? 0),
            'length' => $this->validateLength($field, $value, $params[0] ?? 0),
            'in' => $this->validateIn($field, $value, $params),
            'regex' => $this->validateRegex($field, $value, $params[0] ?? ''),
            'date' => $this->validateDate($field, $value),
            'json' => $this->validateJson($field, $value),
            default => "Unknown validation rule: {$ruleName}",
        };
    }

    /**
     * Extract request data for validation
     *
     * @param Request $request The HTTP request
     * @return array Request data
     */
    private function extractRequestData(Request $request): array
    {
        $data = [];

        // Add query parameters
        $data = array_merge($data, $request->query->all());

        // Add request body data
        if ($request->getContentTypeFormat() === 'application/json') {
            $jsonData = json_decode($request->getContent(), true);
            if (is_array($jsonData)) {
                $data = array_merge($data, $jsonData);
            }
        } else {
            $data = array_merge($data, $request->request->all());
        }

        // Add route parameters
        $data = array_merge($data, $request->attributes->all());

        return $data;
    }

    /**
     * Sanitize value based on validation rules
     *
     * @param mixed $value The value to sanitize
     * @param array|string $rules Validation rules
     * @return mixed Sanitized value
     */
    private function sanitizeValue(mixed $value, array|string $rules): mixed
    {
        $ruleList = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($ruleList as $rule) {
            if (is_string($rule)) {
                $rule = $this->parseRule($rule);
            }

            $ruleName = $rule['name'];

            $value = match ($ruleName) {
                'integer', 'int' => is_numeric($value) ? (int) $value : $value,
                'boolean', 'bool' => $this->toBool($value),
                'string' => is_scalar($value) ? (string) $value : $value,
                'array' => is_string($value) ? [$value] : $value,
                default => $value,
            };
        }

        return $value;
    }

    // Validation rule implementations

    private function validateRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "The {$field} field is required.";
        }
        return null;
    }

    private function validateString(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_string($value) && !is_scalar($value)) {
            return "The {$field} field must be a string.";
        }
        return null;
    }

    private function validateInteger(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_int($value) && !ctype_digit((string) $value)) {
            return "The {$field} field must be an integer.";
        }
        return null;
    }

    private function validateNumeric(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_numeric($value)) {
            return "The {$field} field must be numeric.";
        }
        return null;
    }

    private function validateBoolean(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return "The {$field} field must be a boolean.";
        }
        return null;
    }

    private function validateEmail(string $field, mixed $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} field must be a valid email address.";
        }
        return null;
    }

    private function validateUrl(string $field, mixed $value): ?string
    {
        if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
            return "The {$field} field must be a valid URL.";
        }
        return null;
    }

    private function validateArray(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_array($value)) {
            return "The {$field} field must be an array.";
        }
        return null;
    }

    private function validateMin(string $field, mixed $value, int|float $min): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && $value < $min) {
            return "The {$field} field must be at least {$min}.";
        }

        if (is_string($value) && strlen($value) < $min) {
            return "The {$field} field must be at least {$min} characters.";
        }

        if (is_array($value) && count($value) < $min) {
            return "The {$field} field must have at least {$min} items.";
        }

        return null;
    }

    private function validateMax(string $field, mixed $value, int|float $max): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && $value > $max) {
            return "The {$field} field must not be greater than {$max}.";
        }

        if (is_string($value) && strlen($value) > $max) {
            return "The {$field} field must not be longer than {$max} characters.";
        }

        if (is_array($value) && count($value) > $max) {
            return "The {$field} field must not have more than {$max} items.";
        }

        return null;
    }

    private function validateLength(string $field, mixed $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && strlen($value) !== $length) {
            return "The {$field} field must be exactly {$length} characters.";
        }

        if (is_array($value) && count($value) !== $length) {
            return "The {$field} field must have exactly {$length} items.";
        }

        return null;
    }

    private function validateIn(string $field, mixed $value, array $allowedValues): ?string
    {
        if ($value !== null && !in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            return "The {$field} field must be one of: {$allowed}.";
        }
        return null;
    }

    private function validateRegex(string $field, mixed $value, string $pattern): ?string
    {
        if ($value !== null && !preg_match($pattern, (string) $value)) {
            return "The {$field} field format is invalid.";
        }
        return null;
    }

    private function validateDate(string $field, mixed $value): ?string
    {
        if ($value !== null) {
            try {
                new \DateTime($value);
            } catch (\Exception) {
                return "The {$field} field must be a valid date.";
            }
        }
        return null;
    }

    private function validateJson(string $field, mixed $value): ?string
    {
        if ($value !== null) {
            json_decode((string) $value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return "The {$field} field must be valid JSON.";
            }
        }
        return null;
    }

    /**
     * Convert value to boolean
     *
     * @param mixed $value The value to convert
     * @return bool Boolean value
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
