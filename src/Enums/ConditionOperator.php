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
    case In = 'in';
    case NotIn = 'not_in';
    case Like = 'like';
    case NotLike = 'not_like';
    case Ilike = 'ilike';
    case NotIlike = 'not_ilike';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case HasKey = 'has_key';
    case HasNotKey = 'has_not_key';
}
