<?php

namespace Comhon\EntityRequester\Enums;

enum MathOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
}
