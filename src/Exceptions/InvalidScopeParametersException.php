<?php

namespace Comhon\EntityRequester\Exceptions;

class InvalidScopeParametersException extends InvalidEntityRequestException
{
    public function __construct(string $scopeName)
    {
        parent::__construct('scope_invalid_parameters', ['scope' => $scopeName]);
    }
}
