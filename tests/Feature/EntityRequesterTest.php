<?php

namespace Tests\Feature\Feature;

use App\SimpleEntitySchemaFactory;
use App\SimpleEnumSchemaFactory;
use App\SimpleRequestSchemaFactory;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Factories\Schema\EntitySchemaFactory;
use Comhon\EntityRequester\Factories\Schema\EnumSchemaFactory;
use Comhon\EntityRequester\Factories\Schema\RequestSchemaFactory;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Tests\TestCase;

class EntityRequesterTest extends TestCase
{
    private function getEntitySchemaCacheKey(string $id, bool $json): string
    {
        return 'entity-requester::entity-'.($json ? 'json' : 'object').'::'.$id;
    }

    private function getRequestSchemaCacheKey(string $id, bool $json): string
    {
        return 'entity-requester::request-'.($json ? 'json' : 'object').'::'.$id;
    }

    private function getEnumSchemaCacheKey(string $id, bool $json): string
    {
        return 'entity-requester::enum-'.($json ? 'json' : 'object').'::'.$id;
    }

    private function getEntityTaggedCache(): Repository
    {
        return app(EntitySchemaFactory::class)->getCache();
    }

    private function getRequestTaggedCache(): Repository
    {
        return app(RequestSchemaFactory::class)->getCache();
    }

    private function getEnumTaggedCache(): Repository
    {
        return app(EnumSchemaFactory::class)->getCache();
    }

    public function test_refresh_cache_success()
    {
        config(['entity-requester.use_cache' => true]);
        app(EntitySchemaFactory::class)->get('user');
        app(RequestSchemaFactory::class)->get('user');
        app(EnumSchemaFactory::class)->get('fruit');
        $entityCache = $this->getEntityTaggedCache();
        $requestCache = $this->getRequestTaggedCache();
        $enumCache = $this->getEnumTaggedCache();

        $this->assertInstanceOf(TaggedCache::class, $entityCache);

        $this->assertTrue($entityCache->has($this->getEntitySchemaCacheKey('user', false)));
        $this->assertTrue($entityCache->has($this->getEntitySchemaCacheKey('user', true)));
        $this->assertTrue($requestCache->has($this->getRequestSchemaCacheKey('user', false)));
        $this->assertTrue($requestCache->has($this->getRequestSchemaCacheKey('user', true)));
        $this->assertTrue($enumCache->has($this->getEnumSchemaCacheKey('fruit', false)));
        $this->assertTrue($enumCache->has($this->getEnumSchemaCacheKey('fruit', true)));

        EntityRequester::refreshCache();

        $this->assertFalse($entityCache->has($this->getEntitySchemaCacheKey('user', false)));
        $this->assertFalse($entityCache->has($this->getEntitySchemaCacheKey('user', true)));
        $this->assertFalse($requestCache->has($this->getRequestSchemaCacheKey('user', false)));
        $this->assertFalse($requestCache->has($this->getRequestSchemaCacheKey('user', true)));
        $this->assertFalse($enumCache->has($this->getEnumSchemaCacheKey('fruit', false)));
        $this->assertFalse($enumCache->has($this->getEnumSchemaCacheKey('fruit', true)));
    }

    public function test_refresh_all_entity_schema_not_supported()
    {
        app()->singleton(EntitySchemaFactoryInterface::class, SimpleEntitySchemaFactory::class);

        $this->expectExceptionMessage('Entity schema factory must be instance of CacheableInterface to refresh cache');
        EntityRequester::refreshEntityCache();
    }

    public function test_refresh_all_request_schema_not_supported()
    {
        app()->singleton(RequestSchemaFactoryInterface::class, SimpleRequestSchemaFactory::class);

        $this->expectExceptionMessage('Request schema factory must be instance of CacheableInterface to refresh cache');
        EntityRequester::refreshRequestCache();
    }

    public function test_refresh_all_enum_schema_not_supported()
    {
        app()->singleton(EnumSchemaFactoryInterface::class, SimpleEnumSchemaFactory::class);

        $this->expectExceptionMessage('Enum schema factory must be instance of CacheableInterface to refresh cache');
        EntityRequester::refreshEnumCache();
    }
}
