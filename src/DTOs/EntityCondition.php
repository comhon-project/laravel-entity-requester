<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Enums\MathOperator;

class EntityCondition extends AbstractCondition
{
    public function __construct(
        private string $property,
        private EntityConditionOperator $operator,
        private ?AbstractCondition $filter = null,
        private ?MathOperator $countOperator = null,
        private ?int $count = null,
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getOperator(): EntityConditionOperator
    {
        return $this->operator;
    }

    public function getFilter(): ?AbstractCondition
    {
        return $this->filter;
    }

    public function getCountOperator(): ?MathOperator
    {
        return $this->countOperator;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }
}
