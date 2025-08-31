<?php

namespace Gwack\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Gwack\Core\Exceptions\ValidationException;

/**
 * Enhanced Request class
 *
 * Extends Symfony's Request with framework-specific functionality
 * like validation, parameter extraction, and helper methods.
 *
 * @package Gwack\Http
 */
class Request extends SymfonyRequest
{
    /**
     * Validate request data against rules
     *
     * @param array $rules Validation rules
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(array $rules): array
    {
        $data = $this->getAllData();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $rulesParts = explode('|', $rule);

            foreach ($rulesParts as $rulePart) {
                $error = $this->validateField($field, $value, $rulePart);
                if ($error) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        return $data;
    }

    /**
     * Validate a single field
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return string|null
     */
    private function validateField(string $field, mixed $value, string $rule): ?string
    {
        $rule = trim($rule);

        if ($rule === 'required' && (is_null($value) || $value === '')) {
            return "The {$field} field is required.";
        }

        if ($rule === 'nullable' && is_null($value)) {
            return null;
        }

        if (str_starts_with($rule, 'string') && !is_null($value) && !is_string($value)) {
            return "The {$field} field must be a string.";
        }

        if (str_starts_with($rule, 'integer') && !is_null($value) && !is_int($value) && !ctype_digit((string) $value)) {
            return "The {$field} field must be an integer.";
        }

        if (str_starts_with($rule, 'email') && !is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} field must be a valid email address.";
        }

        return null;
    }

    /**
     * Get all request data (query + body)
     *
     * @return array
     */
    public function getAllData(): array
    {
        $data = $this->query->all();

        if ($this->getContentTypeFormat() === 'json') {
            $body = json_decode($this->getContent(), true);
            if (is_array($body)) {
                $data = array_merge($data, $body);
            }
        } else {
            $data = array_merge($data, $this->request->all());
        }

        return $data;
    }

    /**
     * Get a parameter value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getAllData()[$key] ?? $this->attributes->get($key, $default);
    }

    /**
     * Check if request has a parameter
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $data = $this->getAllData();
        return array_key_exists($key, $data) || $this->attributes->has($key);
    }

    /**
     * Get only specified parameters
     *
     * @param array $keys
     * @return array
     */
    public function only(array $keys): array
    {
        $data = $this->getAllData();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Get all parameters except specified ones
     *
     * @param array $keys
     * @return array
     */
    public function except(array $keys): array
    {
        $data = $this->getAllData();
        return array_diff_key($data, array_flip($keys));
    }
}
