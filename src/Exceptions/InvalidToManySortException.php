<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidToManySortException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        parent::__construct(
            "Invalid \"to many\" sort on property '$propertyName', it must have aggregation function"
        );
    }
}
