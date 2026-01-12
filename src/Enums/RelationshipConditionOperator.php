<?php

namespace Comhon\EntityRequester\Enums;

enum RelationshipConditionOperator: string
{
    case Has = 'has';
    case HasNot = 'has_not';
}
