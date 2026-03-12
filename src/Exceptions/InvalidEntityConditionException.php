<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidEntityConditionException extends RenderableException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
