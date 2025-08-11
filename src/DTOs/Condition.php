<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\ConditionOperator;

class Condition extends AbstractCondition
{
    public function __construct(
        private string $property,
        private ConditionOperator $operator,
        private $value
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getOperator(): ConditionOperator
    {
        return $this->operator;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
