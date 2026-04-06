<?php

namespace Comhon\EntityRequester\Exceptions;

class PropertyNotFoundException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName, string $schemaId)
    {
        parent::__construct('property_not_found_in_schema', ['property' => $propertyName, 'schema' => $schemaId]);
    }
}
