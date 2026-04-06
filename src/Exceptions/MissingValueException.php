<?php

namespace Comhon\EntityRequester\Exceptions;

class MissingValueException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName)
    {
        parent::__construct('property_required', ['property' => $propertyName]);
    }
}
