<?php

namespace Comhon\EntityRequester\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getSchemaDirectory()
 * @method static bool useCache()
 * @method static void refreshCache(?string $schemaId = null)
 *
 * @see \Comhon\EntityRequester\EntityRequester
 */
class EntityRequester extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Comhon\EntityRequester\EntityRequester::class;
    }
}
