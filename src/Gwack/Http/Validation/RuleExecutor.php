<?php

namespace Gwack\Http\Validation;

use Gwack\Http\Request;
use Gwack\Http\Validation\Rules\StringRule;
use Gwack\Http\Validation\Rules\EmailRule;
use Gwack\Http\Validation\Rules\NumberRule;

/**
 * Rule Executor
 *
 * Manages and executes validation rules for the framework.
 * Provides rule execution with caching and optimization.
 *
 * @package Gwack\Http\Validation
 */
class RuleExecutor
{
    /**
     * @var array<string, Rule> Registered validation rules
     */
    private array $rules = [];

    /**
     * @var array Cached rule instances for performance
     */
    private array $ruleCache = [];

    /**
     * @var array Rule aliases for convenient access
     */
    private array $aliases = [];

    /**
     * Constructor - registers default rules
     */
    public function __construct()
    {
        $this->registerDefaultRules();
    }

    /**
     * Register a validation rule
     *
     * @param string $name Rule name/identifier
     * @param Rule $rule Rule instance
     * @param array $aliases Optional aliases for the rule
     * @return void
     */
    public function registerRule(string $name, Rule $rule, array $aliases = []): void
    {
        $this->rules[$name] = $rule;

        // Register aliases
        foreach ($aliases as $alias) {
            $this->aliases[$alias] = $name;
        }

        // Clear cache for this rule
        unset($this->ruleCache[$name]);
        foreach ($aliases as $alias) {
            unset($this->ruleCache[$alias]);
        }
    }

    /**
     * Execute a validation rule on a field
     *
     * @param string $ruleName Name of the rule to execute
     * @param string $field Field name to validate
     * @param Request $request Request containing the data
     * @param array $parameters Optional parameters for the rule
     * @return mixed Validation result (true for success, string for error message)
     */
    public function executeRule(string $ruleName, string $field, Request $request, array $parameters = []): mixed
    {
        $rule = $this->getRule($ruleName, $parameters);

        if (!$rule) {
            return "Unknown validation rule: {$ruleName}";
        }

        try {
            return $rule->executeOnRequest($field, $request);
        } catch (\Exception $e) {
            return "Validation error: " . $e->getMessage();
        }
    }

    /**
     * Execute multiple rules on a field
     *
     * @param array $rules Array of rule names or rule configurations
     * @param string $field Field name to validate
     * @param Request $request Request containing the data
     * @return array Array of validation results
     */
    public function executeRules(array $rules, string $field, Request $request): array
    {
        $results = [];

        foreach ($rules as $ruleName => $parameters) {
            // Handle different rule specification formats
            if (is_int($ruleName)) {
                // Format: [0 => 'required', 1 => 'string:max:255']
                $ruleParts = explode(':', $parameters);
                $ruleName = array_shift($ruleParts);
                $parameters = $this->parseRuleParameters($ruleParts);
            } elseif (is_string($parameters)) {
                // Format: ['required', 'string', 'email']
                $ruleName = $parameters;
                $parameters = [];
            }

            $result = $this->executeRule($ruleName, $field, $request, $parameters);
            $results[$ruleName] = $result;

            // Stop on first failure if configured to do so
            if ($result !== true) {
                break;
            }
        }

        return $results;
    }

    /**
     * Validate an entire request using a rule collection
     *
     * @param RuleCollection $ruleCollection Collection of validation rules
     * @param Request $request Request to validate
     * @return array Validation results grouped by field
     */
    public function validateRequest(RuleCollection $ruleCollection, Request $request): array
    {
        $results = [];

        foreach ($ruleCollection as $field => $fieldRules) {
            $results[$field] = $this->executeRules($fieldRules, $field, $request);
        }

        return $results;
    }

    /**
     * Get a rule instance by name
     *
     * @param string $ruleName Name of the rule
     * @param array $parameters Parameters to configure the rule
     * @return Rule|null Rule instance or null if not found
     */
    public function getRule(string $ruleName, array $parameters = []): ?Rule
    {
        // Check for alias
        if (isset($this->aliases[$ruleName])) {
            $ruleName = $this->aliases[$ruleName];
        }

        // Check if rule exists
        if (!isset($this->rules[$ruleName])) {
            return null;
        }

        // Create cache key including parameters
        $cacheKey = $ruleName . '_' . md5(serialize($parameters));

        // Return cached instance if available
        if (isset($this->ruleCache[$cacheKey])) {
            return $this->ruleCache[$cacheKey];
        }

        // Clone the base rule and apply parameters
        $rule = clone $this->rules[$ruleName];

        // If rule has parameters, create a new instance with those parameters
        if (!empty($parameters)) {
            $ruleClass = get_class($rule);
            $rule = new $ruleClass($ruleName, $parameters);
        }

        // Cache the configured rule instance
        $this->ruleCache[$cacheKey] = $rule;

        return $rule;
    }

    /**
     * Check if a rule is registered
     *
     * @param string $ruleName Name of the rule
     * @return bool True if rule exists, false otherwise
     */
    public function hasRule(string $ruleName): bool
    {
        return isset($this->rules[$ruleName]) || isset($this->aliases[$ruleName]);
    }

    /**
     * Get all registered rule names
     *
     * @return array Array of rule names
     */
    public function getRuleNames(): array
    {
        return array_keys($this->rules);
    }

    /**
     * Get all registered aliases
     *
     * @return array Array mapping aliases to rule names
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Clear rule cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->ruleCache = [];
    }

    /**
     * Register default validation rules
     *
     * @return void
     */
    private function registerDefaultRules(): void
    {
        // Register string validation rule
        $this->registerRule('string', new StringRule('string'), ['str', 'text']);

        // Register email validation rule
        $this->registerRule('email', new EmailRule('email'), ['mail']);

        // Register number validation rule
        $this->registerRule('number', new NumberRule('number'), ['num', 'numeric', 'integer', 'int', 'float']);
    }

    /**
     * Parse rule parameters from string format
     *
     * @param array $parameterParts Array of parameter strings
     * @return array Parsed parameters
     */
    private function parseRuleParameters(array $parameterParts): array
    {
        $parameters = [];

        foreach ($parameterParts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $parameters[trim($key)] = $this->parseParameterValue(trim($value));
            } else {
                // Numeric index for unnamed parameters
                $parameters[] = $this->parseParameterValue(trim($part));
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

        // Array values (comma-separated)
        if (str_contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }

        // String value
        return $value;
    }

    /**
     * Get available rule names
     *
     * @return array Array of available rule names
     */
    public function getAvailableRules(): array
    {
        return array_keys($this->rules);
    }

    /**
     * Get available rule aliases
     *
     * @return array Array of available rule aliases
     */
    public function getAvailableAliases(): array
    {
        return array_keys($this->aliases);
    }
}
