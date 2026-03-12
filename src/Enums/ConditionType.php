<?php

namespace Comhon\EntityRequester\Enums;

enum ConditionType: string
{
    case Condition = 'condition';
    case Group = 'group';
    case EntityCondition = 'entity_condition';
    case Scope = 'scope';
}
