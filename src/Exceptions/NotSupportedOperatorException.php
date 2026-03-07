<?php

namespace Comhon\EntityRequester\Exceptions;

use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;

class NotSupportedOperatorException extends RenderableException
{
    public function __construct(ConditionOperator $operator)
    {
        $manager = app(ConditionOperatorManagerInterface::class);
        $values = array_map(
            fn ($op) => $op->value,
            array_filter(ConditionOperator::cases(), fn ($op) => $manager->isSupported($op)),
        );

        parent::__construct("Not supported condition operator '{$operator->value}', must be one of [".implode(', ', $values).']');
    }
}
