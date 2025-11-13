<?php

namespace Comhon\EntityRequester\Facades;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void authorize(EntityRequest $entityRequest)
 *
 * @see \Comhon\EntityRequester\EntityRequest\Gate
 */
class Gate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Comhon\EntityRequester\Interfaces\RequestGateInterface::class;
    }
}
