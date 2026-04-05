<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidConditionOperatorException extends RenderableException
{
    public function __construct(string $propertyName, array $supportedOperators)
    {
        $values = array_map(fn ($op) => $op->value, $supportedOperators);

        parent::__construct("Invalid property '$propertyName', must be one of [".implode(', ', $values).']');
    }
}
