<?php

namespace Comhon\EntityRequester\Exceptions;

class NotScopableException extends RenderableException
{
    /**
     * @param  string  $propertyName
     */
    public function __construct(string $scopeName)
    {
        parent::__construct("scope '$scopeName' is not valid");
    }
}
