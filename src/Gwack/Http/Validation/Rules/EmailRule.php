<?php

namespace Gwack\Http\Validation\Rules;

use Gwack\Http\Validation\Rule;
use Gwack\Http\Request;

/**
 * Email validation rule
 * 
 * Validates that a field contains a valid email address using multiple validation methods.
 * 
 * @package Gwack\Http\Validation\Rules
 */
class EmailRule extends Rule
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

        // Convert to string if not already
        if (!is_string($value)) {
            if (is_scalar($value)) {
                $value = (string) $value;
            } else {
                return "The {$field} field must be a valid email address.";
            }
        }

        // Trim whitespace
        $value = trim($value);

        // Check if empty after trimming
        if (empty($value)) {
            return "The {$field} field must be a valid email address.";
        }

        // Basic length check (email should not be too long)
        if (strlen($value) > 254) {
            return "The {$field} field must be a valid email address.";
        }

        // Use PHP's built-in email validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return "The {$field} field must be a valid email address.";
        }

        // Additional RFC-compliant validation
        if (!$this->isValidEmailFormat($value)) {
            return "The {$field} field must be a valid email address.";
        }

        // Check for strict validation if requested
        if (isset($parameters['strict']) && $parameters['strict']) {
            if (!$this->strictEmailValidation($value)) {
                return "The {$field} field must be a valid email address.";
            }
        }

        // Check for domain validation if requested
        if (isset($parameters['dns']) && $parameters['dns']) {
            if (!$this->validateEmailDomain($value)) {
                return "The {$field} field must have a valid domain.";
            }
        }

        return true;
    }

    /**
     * Validate email format according to RFC standards
     *
     * @param string $email The email to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidEmailFormat(string $email): bool
    {
        // Split email into local and domain parts
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return false;
        }

        [$local, $domain] = $parts;

        // Validate local part (before @)
        if (!$this->validateLocalPart($local)) {
            return false;
        }

        // Validate domain part (after @)
        if (!$this->validateDomainPart($domain)) {
            return false;
        }

        return true;
    }

    /**
     * Validate the local part of an email address
     *
     * @param string $local The local part to validate
     * @return bool True if valid, false otherwise
     */
    private function validateLocalPart(string $local): bool
    {
        // Local part cannot be empty or too long
        if (empty($local) || strlen($local) > 64) {
            return false;
        }

        // Cannot start or end with a dot
        if ($local[0] === '.' || $local[-1] === '.') {
            return false;
        }

        // Cannot have consecutive dots
        if (str_contains($local, '..')) {
            return false;
        }

        // Check for valid characters (simplified check)
        if (!preg_match('/^[a-zA-Z0-9._%+-]+$/', $local)) {
            return false;
        }

        return true;
    }

    /**
     * Validate the domain part of an email address
     *
     * @param string $domain The domain part to validate
     * @return bool True if valid, false otherwise
     */
    private function validateDomainPart(string $domain): bool
    {
        // Domain cannot be empty or too long
        if (empty($domain) || strlen($domain) > 253) {
            return false;
        }

        // Must contain at least one dot
        if (!str_contains($domain, '.')) {
            return false;
        }

        // Check for valid domain format
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            return false;
        }

        // Cannot start or end with a dot or hyphen
        if (in_array($domain[0], ['.', '-']) || in_array($domain[-1], ['.', '-'])) {
            return false;
        }

        // Split into labels and validate each
        $labels = explode('.', $domain);

        foreach ($labels as $label) {
            if (empty($label) || strlen($label) > 63) {
                return false;
            }

            // Label cannot start or end with hyphen
            if ($label[0] === '-' || $label[-1] === '-') {
                return false;
            }
        }

        return true;
    }

    /**
     * Perform strict email validation
     *
     * @param string $email The email to validate
     * @return bool True if valid, false otherwise
     */
    private function strictEmailValidation(string $email): bool
    {
        // Additional strict validation rules

        // No uppercase letters in local part (some servers are case-sensitive)
        $parts = explode('@', $email);
        $local = $parts[0];

        if ($local !== strtolower($local)) {
            return false;
        }

        return true;
    }

    /**
     * Validate email domain using DNS lookup
     *
     * @param string $email The email to validate
     * @return bool True if domain exists, false otherwise
     */
    private function validateEmailDomain(string $email): bool
    {
        $parts = explode('@', $email);
        $domain = $parts[1];

        // Check for MX record
        if (checkdnsrr($domain, 'MX')) {
            return true;
        }

        // Fallback to A record
        if (checkdnsrr($domain, 'A')) {
            return true;
        }

        return false;
    }
}
