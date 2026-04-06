<?php

namespace Comhon\EntityRequester\Exceptions;

class NotFiltrableException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName)
    {
        parent::__construct('property_not_filtrable', ['property' => $propertyName]);
    }
}
