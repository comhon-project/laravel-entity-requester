<?php

namespace Comhon\EntityRequester\Factories;

use Comhon\EntityRequester\DTOs\Schema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;

class SchemaFactory extends AbstractJsonFileFactory implements SchemaFactoryInterface
{
    public function get(string $id): Schema
    {
        return parent::get($id);
    }

    protected function getName(): string
    {
        return 'schema';
    }

    protected function instanciate(array $data): object
    {
        return new Schema($data);
    }

    protected function getDirectory(): string
    {
        return EntityRequester::getSchemaDirectory();
    }
}
