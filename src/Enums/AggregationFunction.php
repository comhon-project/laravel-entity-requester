<?php

namespace Comhon\EntityRequester\Enums;

enum AggregationFunction: string
{
    case Count = 'count';
    case Sum = 'sum';
    case Avg = 'avg';
    case Min = 'min';
    case Max = 'max';
}
