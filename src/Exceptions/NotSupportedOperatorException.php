<?php

namespace Comhon\EntityRequester\Exceptions;

use Comhon\EntityRequester\Enums\ConditionOperator;

class NotSupportedOperatorException extends RenderableException
{
    public function __construct(ConditionOperator $operator)
    {
        $operators = ConditionOperator::getSupportedOperators();
        $values = array_map(fn ($operator) => $operator->value, $operators);

        parent::__construct("Not supported condition operator '{$operator->value}', must be one of [".implode(', ', $values).']');
    }
}
