<?php

namespace Comhon\EntityRequester\Exceptions;

class NotSortableException extends RenderableException
{
    public function __construct(string $propertyName)
    {
        parent::__construct("Property '$propertyName' is not sortable");
    }
}
