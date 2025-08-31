<?php

namespace Gwack\Http\Validation;

use Gwack\Http\Request;
use Gwack\Core\Exceptions\ValidationException;

/**
 * Request Validator
 *
 * Request validation using the framework's rule system.
 * Provides seamless integration between HTTP requests and validation rules.
 *
 * @package Gwack\Http\Validation
 */
class RequestValidator
{
    /**
     * @var RuleExecutor The rule executor instance
     */
    private RuleExecutor $ruleExecutor;

    /**
     * Constructor
     *
     * @param RuleExecutor $ruleExecutor The rule executor instance
     */
    public function __construct(RuleExecutor $ruleExecutor)
    {
        $this->ruleExecutor = $ruleExecutor;
    }

    /**
     * Validate a request against a set of rules
     *
     * @param Request $request The request to validate
     * @param array $rules Array of field => rules mappings
     * @param bool $stopOnFirstFailure Whether to stop on first validation failure
     * @return array Validation results
     * @throws ValidationException If validation fails and exceptions are enabled
     */
    public function validate(Request $request, array $rules, bool $stopOnFirstFailure = false): array
    {
        $errors = [];
        $validatedData = [];
        $data = $request->getAllData();

        foreach ($rules as $field => $fieldRules) {
            $fieldErrors = $this->validateField($field, $fieldRules, $request);

            if (!empty($fieldErrors)) {
                $errors[$field] = $stopOnFirstFailure ? $fieldErrors[0] : $fieldErrors;

                if ($stopOnFirstFailure) {
                    break;
                }
            } else {
                // Field is valid, add to validated data
                $validatedData[$field] = $data[$field] ?? null;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validatedData
        ];
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name
     * @param array|string $rules Field validation rules
     * @param Request $request The request instance
     * @return array Array of error messages (empty if valid)
     */
    public function validateField(string $field, array|string $rules, Request $request): array
    {
        $errors = [];

        // Normalize rules to array format
        $ruleList = is_string($rules) ? $this->parseRuleString($rules) : $rules;

        foreach ($ruleList as $ruleName => $parameters) {
            // Handle different rule formats
            if (is_int($ruleName)) {
                // Format: ['string', 'email'] or ['string:min=10']
                $ruleString = $parameters;
                if (str_contains($ruleString, ':')) {
                    // Parse 'string:min=10' format
                    [$ruleName, $paramString] = explode(':', $ruleString, 2);
                    $parameters = $this->parseParameters($paramString);
                } else {
                    $ruleName = $ruleString;
                    $parameters = [];
                }
            } elseif (is_string($ruleName) && is_array($parameters)) {
                // Format: ['string' => ['min' => 10]]
                // Already in correct format
            } elseif (is_string($parameters)) {
                // This shouldn't happen with proper format, but handle it
                $ruleName = $parameters;
                $parameters = [];
            } elseif (!is_array($parameters)) {
                // Ensure parameters is always an array
                $parameters = [];
            }

            // Execute the rule
            $result = $this->ruleExecutor->executeRule($ruleName, $field, $request, $parameters);

            if ($result !== true) {
                $errors[] = $result;
                // Stop on first error for this field
                break;
            }
        }

        return $errors;
    }

    /**
     * Validate request and throw exception on failure
     *
     * @param Request $request The request to validate
     * @param array $rules Array of field => rules mappings
     * @return array Validated data
     * @throws ValidationException If validation fails
     */
    public function validateOrThrow(Request $request, array $rules): array
    {
        $result = $this->validate($request, $rules);

        if (!$result['valid']) {
            throw new ValidationException('Validation failed', $result['errors']);
        }

        return $result['data'];
    }

    /**
     * Check if a request passes validation
     *
     * @param Request $request The request to validate
     * @param array $rules Array of field => rules mappings
     * @return bool True if valid, false otherwise
     */
    public function passes(Request $request, array $rules): bool
    {
        $result = $this->validate($request, $rules, true);
        return $result['valid'];
    }

    /**
     * Check if a request fails validation
     *
     * @param Request $request The request to validate
     * @param array $rules Array of field => rules mappings
     * @return bool True if invalid, false if valid
     */
    public function fails(Request $request, array $rules): bool
    {
        return !$this->passes($request, $rules);
    }

    /**
     * Parse a rule string into array format
     *
     * @param string $ruleString Rule string (e.g., 'required|string:max=255|email')
     * @return array Parsed rules
     */
    private function parseRuleString(string $ruleString): array
    {
        $rules = [];
        $ruleParts = explode('|', $ruleString);

        foreach ($ruleParts as $rulePart) {
            $rulePart = trim($rulePart);

            if (str_contains($rulePart, ':')) {
                [$ruleName, $paramString] = explode(':', $rulePart, 2);
                $rules[$ruleName] = $this->parseParameters($paramString);
            } else {
                $rules[] = $rulePart;
            }
        }

        return $rules;
    }

    /**
     * Parse parameter string into array
     *
     * @param string $paramString Parameter string (e.g., 'max=255,min=2')
     * @return array Parsed parameters
     */
    private function parseParameters(string $paramString): array
    {
        $parameters = [];
        $parts = explode(',', $paramString);

        foreach ($parts as $part) {
            $part = trim($part);

            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $parameters[trim($key)] = $this->parseParameterValue(trim($value));
            } else {
                $parameters[] = $this->parseParameterValue($part);
            }
        }

        return $parameters;
    }

    /**
     * Parse a parameter value to appropriate type
     *
     * @param string $value Raw parameter value
     * @return mixed Parsed value
     */
    private function parseParameterValue(string $value): mixed
    {
        // Boolean values
        if (in_array(strtolower($value), ['true', '1', 'yes', 'on'])) {
            return true;
        }

        if (in_array(strtolower($value), ['false', '0', 'no', 'off'])) {
            return false;
        }

        // Numeric values
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // String value
        return $value;
    }
}