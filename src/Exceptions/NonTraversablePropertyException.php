<?php

namespace Comhon\EntityRequester\Exceptions;

class NonTraversablePropertyException extends InvalidEntityRequestException
{
    public function __construct(string $propertyId)
    {
        parent::__construct('property_not_traversable', ['property' => $propertyId]);
    }
}
