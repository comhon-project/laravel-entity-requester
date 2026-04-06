<?php

namespace Comhon\EntityRequester\Exceptions;

class SchemaNotFoundException extends InvalidEntityRequestException
{
    public function __construct(string $name, string $id)
    {
        parent::__construct('schema_not_found', ['name' => $name, 'id' => $id]);
    }
}
