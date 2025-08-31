<?php

namespace Gwack\Http\Validation;

use Gwack\Http\Request;

abstract class Rule
{
    /**
     * @var string The name of the validation rule
     */
    private string $name;

    /**
     * @var array Additional parameters for the rule
     */
    private array $parameters;

    /**
     * Rule constructor.
     *
     * @param string $name The name of the validation rule
     * @param array $parameters Additional parameters for the rule
     */
    public function __construct(string $name, array $parameters = [])
    {
        $this->name = $name;
        $this->parameters = $parameters;
    }

    /**
     * Execute the validation rule on a specific field in the request
     *
     * @param string $field The field to validate
     * @param Request $request The request containing the data
     * @return mixed The result of the validation, true or an error message
     */
    abstract public function executeOnRequest(string $field, Request $request): mixed;

    /**
     * Get the name of the validation rule
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the parameters for the validation rule
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}