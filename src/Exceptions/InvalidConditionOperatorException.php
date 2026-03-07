<?php

namespace Comhon\EntityRequester\Exceptions;

use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;

class InvalidConditionOperatorException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        $manager = app(ConditionOperatorManagerInterface::class);
        $values = array_map(
            fn ($op) => $op->value,
            array_filter(ConditionOperator::cases(), fn ($op) => $manager->isSupported($op)),
        );

        parent::__construct("Invalid property '$propertyName', must be one of [".implode(', ', $values).']');
    }
}
