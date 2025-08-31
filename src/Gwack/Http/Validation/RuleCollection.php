<?php

namespace Gwack\Http\Validation;

use Gwack\Core\Structure\Collection;
use Gwack\Http\Exceptions\InvalidValidationRule;

class RuleCollection extends Collection
{
    /**
     * Add a validation rule for a specific field
     *
     * @param string $field The field to validate
     * @param string $rule The validation rule
     * @return void
     */
    public function addRule(string $field, string $rule, array $params): void
    {
        if (!$this->offsetExists($field)) {
            $this->offsetSet($field, []);
        }
        $rules = $this->offsetGet($field);
        $rules[] = [$rule, $params];
        $this->offsetSet($field, $rules);
    }

    /**
     * Get all rules for a specific field
     *
     * @param string $field The field to get rules for
     * @return array|null
     */
    public function getRules(string $field): ?array
    {
        return $this->offsetGet($field) ?? null;
    }

    /**
     * Check if a field has any validation rules
     *
     * @param string $field The field to check
     * @return bool
     */
    public function hasRules(string $field): bool
    {
        return $this->offsetExists($field) && !empty($this->offsetGet($field));
    }

    /**
     * Add multiple rules from an array
     *
     * @param string $field The field to validate
     * @param array $rules Associative array of field => rule pairs
     * @return void
     */
    public function addArrayRules(string $field, array $rules): void
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $params = explode(':', $rule);
                if (count($params) === 2 && trim($params[0]) === $field) {
                    $this->addRule($field, trim($params[1]), $params);
                } else {
                    $this->addRule($field, trim($rule), []);
                }
            } else {
                throw new InvalidValidationRule($rule);
            }
        }
    }

    /**
     * Add multiple rules from a string ex: "string|email|max:255|unique:users,email"
     *
     * @param string $field The field to validate
     * @param string $rulesString A string of rules in the format "field:rule1|rule2,field2:rule1"
     * @return void
     */
    public function addStringRules(string $field, string $rulesString): void
    {
        $rules = explode('|', $rulesString);
        foreach ($rules as $rule) {
            $params = explode(':', $rule);
            if (count(value: $params) === 2 && trim($params[0]) === $field) {
                $this->addRule($field, trim($params[1]), $params);
            }
        }
    }
}