<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function test_schema_content()
    {
        $schema = app(SchemaFactoryInterface::class)->get('user');

        $this->assertEquals('user', $schema->getId());
        $this->assertCount(21, $schema->getProperties());

        $property = ['id' => 'name', 'type' => 'string', 'nullable' => false];
        $this->assertEquals($property, $schema->getProperty('name'));
        $this->assertEquals(true, $schema->hasProperty('name'));

        $this->assertEquals(null, $schema->getProperty('foo'));
        $this->assertEquals(false, $schema->hasProperty('foo'));

        $this->assertEquals(true, $schema->isFiltrable('first_name'));
        $this->assertEquals(false, $schema->isFiltrable('foo'));

        $this->assertEquals(true, $schema->isScopable('carbon'));
        $this->assertEquals(false, $schema->isScopable('foobar'));

        $this->assertEquals(true, $schema->isSortable('birth_date'));
        $this->assertEquals(false, $schema->isSortable('foo'));
    }
}
