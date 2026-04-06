<?php

namespace Comhon\EntityRequester\Exceptions;

class NotSortableException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName)
    {
        parent::__construct('property_not_sortable', ['property' => $propertyName]);
    }
}
