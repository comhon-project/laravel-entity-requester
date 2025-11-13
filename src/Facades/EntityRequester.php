<?php

namespace Comhon\EntityRequester\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string getEntitySchemaDirectory()
 * @method static string getRequestSchemaDirectory()
 * @method static bool useCache()
 * @method static void refreshCache()
 * @method static void refreshEntityCache(?string $schemaId = null)
 * @method static void refreshRequestCache(?string $schemaId = null)
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
