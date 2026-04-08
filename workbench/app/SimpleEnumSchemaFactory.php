<?php

namespace App;

use Comhon\EntityRequester\DTOs\EnumSchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;

class SimpleEnumSchemaFactory implements EnumSchemaFactoryInterface
{
    public function get(string $id): EnumSchema
    {
        return new EnumSchema(json_decode(file_get_contents($this->getPath($id)), true));
    }

    private function getPath(string $id): string
    {
        return EntityRequester::getEnumSchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }
}
