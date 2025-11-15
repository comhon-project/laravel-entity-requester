<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\Factories\EnumSchemaFactory;
use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class EnumSchemaFactoryTest extends TestCase
{
    private function getTaggedCache(): Repository
    {
        return app(EnumSchemaFactory::class)->getCache();
    }

    private function getEnumSchemaUserCacheKey(bool $json): string
    {
        return 'entity-requester::enum-'.($json ? 'json' : 'object').'::fruit';
    }

    public function test_get_request_schema_with_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getEnumSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getEnumSchemaUserCacheKey(false)));

        app(EnumSchemaFactoryInterface::class)->get('fruit');

        $this->assertTrue($this->getTaggedCache()->has($this->getEnumSchemaUserCacheKey(true)));
        $this->assertTrue($this->getTaggedCache()->has($this->getEnumSchemaUserCacheKey(false)));
    }
}
