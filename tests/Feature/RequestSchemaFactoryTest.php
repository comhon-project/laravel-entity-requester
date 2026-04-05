<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\DTOs\RequestSchema;
use Comhon\EntityRequester\Factories\Schema\RequestSchemaFactory;
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

    public function test_get_child_schema_registers_via_parent()
    {
        $factory = app(RequestSchemaFactoryInterface::class);
        $factory->get('user');

        $childSchema = $factory->get('user.metadata');

        $this->assertInstanceOf(RequestSchema::class, $childSchema);
        $this->assertTrue($childSchema->isFiltrable('label'));
        $this->assertTrue($childSchema->isFiltrable('address'));
    }

    public function test_get_nested_child_schema_registers_via_parent()
    {
        $factory = app(RequestSchemaFactoryInterface::class);
        $factory->get('user');

        $childSchema = $factory->get('user.address');

        $this->assertInstanceOf(RequestSchema::class, $childSchema);
        $this->assertTrue($childSchema->isFiltrable('city'));
        $this->assertTrue($childSchema->isFiltrable('zip'));
    }

    public function test_refresh_with_id_also_refreshes_entities()
    {
        $factory = app(RequestSchemaFactoryInterface::class);
        $factory->get('user');

        $metadataSchema = $factory->get('user.metadata');
        $addressSchema = $factory->get('user.address');

        $factory->refresh('user');

        // parent must be re-loaded to re-register child entities
        $factory->get('user');

        $this->assertNotSame($metadataSchema, $factory->get('user.metadata'));
        $this->assertNotSame($addressSchema, $factory->get('user.address'));
    }
}
