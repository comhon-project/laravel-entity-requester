<?php

namespace Comhon\EntityRequester\Enums;

enum EntityConditionOperator: string
{
    case Has = 'has';
    case HasNot = 'has_not';
}
