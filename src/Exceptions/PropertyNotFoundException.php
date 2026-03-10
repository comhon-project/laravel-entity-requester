<?php

namespace Comhon\EntityRequester\Exceptions;

class PropertyNotFoundException extends RenderableException
{
    public function __construct(string $propertyName, string $schemaId)
    {
        parent::__construct("Property '$propertyName' not found in schema '$schemaId'");
    }
}
