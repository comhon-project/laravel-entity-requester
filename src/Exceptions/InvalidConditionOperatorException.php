<?php

namespace Comhon\EntityRequester\Exceptions;

use Comhon\EntityRequester\Enums\ConditionOperator;

class InvalidConditionOperatorException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        $operators = ConditionOperator::getSupportedOperators();
        $values = array_map(fn ($operator) => $operator->value, $operators);

        parent::__construct("Invalid property '$propertyName', must be one of [".implode(', ', $values).']');
    }
}
