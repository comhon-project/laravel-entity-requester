<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\Enums\ConditionOperator;

interface ConditionOperatorManagerInterface
{
    public function getSqlOperator(ConditionOperator $operator): string;

    public function isSupported(ConditionOperator $operator): bool;

    public function getOperatorsForPropertyType(string $type): array;
}
