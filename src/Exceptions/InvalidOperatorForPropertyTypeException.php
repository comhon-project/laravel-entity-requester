<?php

namespace Comhon\EntityRequester\Exceptions;

use Comhon\EntityRequester\Enums\ConditionOperator;

class InvalidOperatorForPropertyTypeException extends RenderableException
{
    public function __construct(ConditionOperator $operator, string $propertyType, array $allowedOperators)
    {
        $values = array_map(fn ($op) => $op->value, $allowedOperators);

        parent::__construct("Condition operator '{$operator->value}' is not valid for '{$propertyType}' property type, must be one of [".implode(', ', $values).']');
    }
}
