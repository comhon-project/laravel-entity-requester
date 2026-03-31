<?php

namespace Comhon\EntityRequester\Exceptions;

class MorphEntitiesRequiredException extends \Exception
{
    public function __construct(string $propertyId)
    {
        parent::__construct("Property '$propertyId' is a morph_to relation and requires explicit entities");
    }
}
