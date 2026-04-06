<?php

namespace Comhon\EntityRequester\Exceptions;

use Comhon\EntityRequester\Enums\ConditionOperator;

class InvalidOperatorForPropertyTypeException extends InvalidEntityRequestException
{
    public function __construct(ConditionOperator $operator, string $propertyType, array $allowedOperators)
    {
        $values = implode(', ', array_map(fn ($op) => $op->value, $allowedOperators));

        parent::__construct('operator_not_valid_for_type', [
            'operator' => $operator->value,
            'type' => $propertyType,
            'values' => $values,
        ]);
    }
}
