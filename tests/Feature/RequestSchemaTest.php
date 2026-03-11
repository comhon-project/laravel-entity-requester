<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\DTOs\RequestSchema;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
use Tests\TestCase;

class RequestSchemaTest extends TestCase
{
    public function test_schema_content()
    {
        $schema = app(RequestSchemaFactoryInterface::class)->get('user');
        $this->assertEquals('user', $schema->getData()['id']);
        $this->assertEquals('user', $schema->getId());

        $this->assertEquals(true, $schema->isFiltrable('first_name'));
        $this->assertEquals(false, $schema->isFiltrable('foo'));

        $this->assertEquals(true, $schema->isScopable('carbon'));
        $this->assertEquals(false, $schema->isScopable('foobar'));

        $this->assertEquals(true, $schema->isSortable('birth_date'));
        $this->assertEquals(false, $schema->isSortable('foo'));
    }

    public function test_schema_entities()
    {
        $schema = app(RequestSchemaFactoryInterface::class)->get('user');

        $entities = $schema->getEntities();
        $this->assertCount(2, $entities);
        $this->assertArrayHasKey('metadata', $entities);
        $this->assertArrayHasKey('address', $entities);
        $this->assertInstanceOf(RequestSchema::class, $entities['metadata']);
        $this->assertInstanceOf(RequestSchema::class, $entities['address']);
    }

    public function test_schema_inline_entity_filtrable_sortable()
    {
        $schema = app(RequestSchemaFactoryInterface::class)->get('user');

        $metadata = $schema->getEntities()['metadata'];
        $this->assertTrue($metadata->isFiltrable('label'));
        $this->assertTrue($metadata->isFiltrable('address'));
        $this->assertFalse($metadata->isFiltrable('foo'));

        $this->assertTrue($metadata->isSortable('label'));
        $this->assertTrue($metadata->isSortable('address'));
        $this->assertFalse($metadata->isSortable('foo'));

        $address = $schema->getEntities()['address'];
        $this->assertTrue($address->isFiltrable('city'));
        $this->assertTrue($address->isFiltrable('zip'));
        $this->assertFalse($address->isFiltrable('foo'));
    }

    public function test_schema_without_entities()
    {
        $schema = new RequestSchema([
            'id' => 'test',
            'filtrable' => ['properties' => ['name'], 'scopes' => []],
            'sortable' => ['name'],
        ]);

        $this->assertEmpty($schema->getEntities());
    }
}
