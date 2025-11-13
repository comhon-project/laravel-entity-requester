<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\Factories\RequestSchemaFactory;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class RequestSchemaFactoryTest extends TestCase
{
    private function getTaggedCache(): Repository
    {
        return app(RequestSchemaFactory::class)->getCache();
    }

    private function getRequestSchemaUserCacheKey(bool $json): string
    {
        return 'entity-requester::request-'.($json ? 'json' : 'object').'::user';
    }

    public function test_get_request_schema_with_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getRequestSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getRequestSchemaUserCacheKey(false)));

        $schema = app(RequestSchemaFactoryInterface::class)->get('user');

        $this->assertTrue($this->getTaggedCache()->has($this->getRequestSchemaUserCacheKey(true)));
        $this->assertTrue($this->getTaggedCache()->has($this->getRequestSchemaUserCacheKey(false)));
    }
}
