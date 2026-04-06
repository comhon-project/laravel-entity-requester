<?php

namespace Comhon\EntityRequester\Exceptions;

class MalformedValueException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName, string $expectedType)
    {
        parent::__construct('property_invalid_type', ['property' => $propertyName, 'type' => $expectedType]);
    }
}
