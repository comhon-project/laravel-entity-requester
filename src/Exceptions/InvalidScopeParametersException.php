<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidScopeParametersException extends RenderableException
{
    /**
     * @param  string  $propertyName
     */
    public function __construct(string $scopeName)
    {
        parent::__construct("invalid '$scopeName' scope parameters");
    }
}
