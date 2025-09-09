<?php

namespace Comhon\EntityRequester\Enums;

enum AggregationFunction: string
{
    case Count = 'COUNT';
    case Sum = 'SUM';
    case Avg = 'AVG';
    case Min = 'MIN';
    case Max = 'MAX';
}
