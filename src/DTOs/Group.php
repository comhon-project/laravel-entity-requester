<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\GroupOperator;

class Group extends AbstractCondition
{
    /**
     * @var AbstractCondition[]
     */
    private array $conditions = [];

    public function __construct(private GroupOperator $operator) {}

    public function getOperator(): GroupOperator
    {
        return $this->operator;
    }

    /**
     * @return AbstractCondition[]
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function add(AbstractCondition $condition): static
    {
        $this->conditions[] = $condition;

        return $this;
    }
}
