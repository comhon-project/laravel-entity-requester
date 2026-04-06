<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidConditionOperatorException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName, array $supportedOperators)
    {
        $values = implode(', ', array_map(fn ($op) => $op->value, $supportedOperators));

        parent::__construct('property_invalid_operator', ['property' => $propertyName, 'values' => $values]);
    }
}
