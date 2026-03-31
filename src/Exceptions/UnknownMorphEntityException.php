<?php

namespace Comhon\EntityRequester\Exceptions;

class UnknownMorphEntityException extends \Exception
{
    public function __construct(string $entityName)
    {
        parent::__construct("Entity '$entityName' is not a valid entity name");
    }
}
