<?php

namespace Tests\Feature\Feature;

use App\SimpleSchemaFactory;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Tests\TestCase;

class EntityRequesterTest extends TestCase
{
    public function test_refresh_all_schema_not_supported()
    {
        app()->singleton(SchemaFactoryInterface::class, SimpleSchemaFactory::class);

        $this->expectExceptionMessage('SchemaFactory must be instance of CacheableInterface to refresh cache');
        EntityRequester::refreshCache();
    }
}
