<?php

namespace Comhon\EntityRequester\Enums;

enum RelationshipConditionOperator: string
{
    case Has = 'HAS';
    case HasNot = 'HAS_NOT';
}
