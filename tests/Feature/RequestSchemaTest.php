<?php

namespace Tests\Feature\Feature;

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
}
