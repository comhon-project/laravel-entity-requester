<?php

namespace Comhon\EntityRequester\Exceptions;

class SchemaNotFoundException extends RenderableException
{
    public function __construct(string $schemaId)
    {
        parent::__construct("Schema '$schemaId' not found");
    }
}
