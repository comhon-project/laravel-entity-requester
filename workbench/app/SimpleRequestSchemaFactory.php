<?php

namespace App;

use Comhon\EntityRequester\DTOs\RequestSchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;

class SimpleRequestSchemaFactory implements RequestSchemaFactoryInterface
{
    public function get(string $id): RequestSchema
    {
        return new RequestSchema(json_decode(file_get_contents($this->getPath($id)), true));
    }

    private function getPath(string $id): string
    {
        return EntityRequester::getEntitySchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }
}
