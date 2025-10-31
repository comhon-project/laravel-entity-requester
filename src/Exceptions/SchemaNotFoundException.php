<?php

namespace Comhon\EntityRequester\Exceptions;

class SchemaNotFoundException extends RenderableException
{
    public function __construct(string $name, string $id)
    {
        parent::__construct("$name '$id' not found");
    }
}
