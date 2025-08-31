<?php

namespace Gwack\Http\Exceptions;


class InvalidValidationRule extends \Exception
{
    protected mixed $rule;

    protected $message = 'Invalid validation rule provided.';

    public function __construct(mixed $rule)
    {
        $this->rule = $rule;
        parent::__construct("The validation rule '{$rule}' is invalid.");
    }

    public function getRule(): mixed
    {
        return $this->rule;
    }
}
