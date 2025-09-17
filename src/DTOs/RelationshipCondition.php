<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\MathOperator;
use Comhon\EntityRequester\Enums\RelationshipConditionOperator;

class RelationshipCondition extends AbstractCondition
{
    public function __construct(
        private string $property,
        private RelationshipConditionOperator $operator,
        private ?AbstractCondition $filter = null,
        private MathOperator $countOperator = MathOperator::GreaterThanOrEqual,
        private int $count = 1,
    ) {}

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getOperator(): RelationshipConditionOperator
    {
        return $this->operator;
    }

    public function getFilter(): ?AbstractCondition
    {
        return $this->filter;
    }

    public function getCountOperator(): MathOperator
    {
        return $this->countOperator;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
