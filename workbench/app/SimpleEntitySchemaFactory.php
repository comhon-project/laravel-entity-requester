<?php

namespace App;

use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;

class SimpleEntitySchemaFactory implements EntitySchemaFactoryInterface
{
    public function get(string $id): EntitySchema
    {
        return new EntitySchema(json_decode(file_get_contents($this->getPath($id)), true));
    }

    private function getPath(string $id): string
    {
        return EntityRequester::getEntitySchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }
}
