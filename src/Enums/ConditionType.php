<?php

namespace Comhon\EntityRequester\Enums;

enum ConditionType: string
{
    case Condition = 'condition';
    case Group = 'group';
    case RelationshipCondition = 'relationship_condition';
    case Scope = 'scope';
}
