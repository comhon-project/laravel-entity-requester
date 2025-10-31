<?php

namespace Comhon\EntityRequester\Facades;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void validate(EntityRequest $entityRequest)
 *
 * @see \Comhon\EntityRequester\EntityRequest\AccessValidator
 */
class AccessValidator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Comhon\EntityRequester\Interfaces\AccessValidatorInterface::class;
    }
}
