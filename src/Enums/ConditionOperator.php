<?php

namespace Comhon\EntityRequester\Enums;

enum ConditionOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case In = 'IN';
    case NotIn = 'NOT IN';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
}
