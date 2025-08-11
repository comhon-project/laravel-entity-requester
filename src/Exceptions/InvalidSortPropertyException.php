<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidSortPropertyException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        parent::__construct("Invalid sort property '$propertyName'");
    }
}
