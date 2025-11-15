<?php

namespace Tests\Feature\Feature;

use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;
use Tests\TestCase;

class EnumSchemaTest extends TestCase
{
    public function test_schema_content()
    {
        $schema = app(EnumSchemaFactoryInterface::class)->get('status');
        $this->assertEquals('status', $schema->getData()['id']);
        $this->assertEquals('status', $schema->getId());
        $this->assertEquals('integer', $schema->getType());

        $this->assertCount(3, $schema->getCases());

        $this->assertEquals(['id' => 1, 'name' => 'pending'], $schema->getCase(1));
        $this->assertEquals(true, $schema->hasCase(1));
    }
}
