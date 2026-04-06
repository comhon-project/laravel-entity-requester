<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidEntityConditionException extends InvalidEntityRequestException
{
    public function __construct(string $message, array $params = [])
    {
        parent::__construct($message, $params);
    }
}
