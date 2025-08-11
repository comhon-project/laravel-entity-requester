<?php

namespace Comhon\EntityRequester\Exceptions;

class NotFiltrableException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        parent::__construct("Property '$propertyName' is not filtrable");
    }
}
