<?php

namespace Comhon\EntityRequester\Exceptions;

class MorphEntitiesRequiredException extends InvalidEntityRequestException
{
    public function __construct(string $propertyId)
    {
        parent::__construct('morph_requires_entities', ['property' => $propertyId]);
    }
}
