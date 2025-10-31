<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\Factories\RequestAccessFactory;
use Comhon\EntityRequester\Interfaces\RequestAccessFactoryInterface;
use Illuminate\Cache\Repository;
use Tests\TestCase;

class RequestAccessFactoryTest extends TestCase
{
    private function getTaggedCache(): Repository
    {
        return app(RequestAccessFactory::class)->getCache();
    }

    private function getRequestAccessUserCacheKey(bool $json): string
    {
        return 'entity-requester::request-access-'.($json ? 'json' : 'object').'::user';
    }

    public function test_get_request_access_with_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getRequestAccessUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getRequestAccessUserCacheKey(false)));

        $schema = app(RequestAccessFactoryInterface::class)->get('user');

        $this->assertTrue($this->getTaggedCache()->has($this->getRequestAccessUserCacheKey(true)));
        $this->assertTrue($this->getTaggedCache()->has($this->getRequestAccessUserCacheKey(false)));
    }
}
