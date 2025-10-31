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

        $this->assertEquals('foo', $schema->getScope('foo')['id']);
        $this->assertEquals(true, $schema->hasScope('foo'));

        $this->assertEquals(null, $schema->getScope('bar'));
        $this->assertEquals(false, $schema->hasScope('bar'));

        $this->assertEquals(
            [['property' => 'name'], ['property' => 'first_name']],
            $schema->getDefaultSort()
        );
    }
}
