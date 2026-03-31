<?php

namespace Comhon\EntityRequester\Exceptions;

class NonTraversablePropertyException extends RenderableException
{
    public function __construct(string $propertyId)
    {
        parent::__construct("Property '$propertyId' is not traversable");
    }
}
