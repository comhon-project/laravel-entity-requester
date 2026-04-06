<?php

namespace Comhon\EntityRequester\Exceptions;

class NotScopableException extends InvalidEntityRequestException
{
    public function __construct(string $scopeName)
    {
        parent::__construct('scope_not_valid', ['scope' => $scopeName]);
    }
}
