<?php

namespace App;

use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Comhon\EntityRequester\Schema\Schema;

class SimpleSchemaFactory implements SchemaFactoryInterface
{
    public function get(string $id): Schema
    {
        return new Schema(json_decode(file_get_contents($this->getPath($id)), true));
    }

    private function getPath(string $id): string
    {
        return EntityRequester::getSchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }
}
