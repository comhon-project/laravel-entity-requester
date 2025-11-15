<?php

namespace Comhon\EntityRequester\Factories;

use Comhon\EntityRequester\DTOs\EnumSchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;

class EnumSchemaFactory extends AbstractJsonFileFactory implements EnumSchemaFactoryInterface
{
    public function get(string $id): EnumSchema
    {
        return parent::get($id);
    }

    protected function getName(): string
    {
        return 'enum';
    }

    protected function instanciate(array $data): object
    {
        return new EnumSchema($data);
    }

    protected function getDirectory(): string
    {
        return EntityRequester::getEnumSchemaDirectory();
    }
}
