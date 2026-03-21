<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Enums\MathOperator;

class MorphCondition extends EntityCondition
{
    public function __construct(
        string $property,
        EntityConditionOperator $operator,
        private array $entities,
        ?AbstractCondition $filter = null,
        ?MathOperator $countOperator = null,
        ?int $count = null,
    ) {
        parent::__construct($property, $operator, $filter, $countOperator, $count);
    }

    public function getEntities(): array
    {
        return $this->entities;
    }
}
