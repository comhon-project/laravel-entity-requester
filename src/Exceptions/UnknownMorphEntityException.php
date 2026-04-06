<?php

namespace Comhon\EntityRequester\Exceptions;

class UnknownMorphEntityException extends InvalidEntityRequestException
{
    public function __construct(string $entityName)
    {
        parent::__construct('entity_not_valid', ['entity' => $entityName]);
    }
}
