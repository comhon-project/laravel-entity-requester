<?php

namespace Comhon\EntityRequester\Exceptions;

class MissingValueException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        parent::__construct("Property '$propertyName' is required");
    }
}
