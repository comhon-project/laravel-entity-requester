<?php

namespace Comhon\EntityRequester\Exceptions;

class MultipleUnsafeAggregationSortException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName)
    {
        parent::__construct('multiple_unsafe_aggregation_sort', ['property' => $propertyName]);
    }
}
