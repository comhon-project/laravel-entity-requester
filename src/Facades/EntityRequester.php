<?php

namespace Comhon\EntityRequester\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Comhon\EntityRequester\EntityRequester
 */
class EntityRequester extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Comhon\EntityRequester\EntityRequester::class;
    }
}
