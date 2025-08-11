<?php

namespace Comhon\EntityRequester\Exceptions;

class MalformedValueException extends RenderableException
{
    public function __construct(string $propertyName, string $expectedType)
    {
        parent::__construct("Invalid property '$propertyName', must be a $expectedType");
    }
}
