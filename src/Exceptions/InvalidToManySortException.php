<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidToManySortException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName)
    {
        parent::__construct('to_many_sort_requires_aggregation', ['property' => $propertyName]);
    }
}
