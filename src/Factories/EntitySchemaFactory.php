<?php

namespace Comhon\EntityRequester\Factories;

use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;

class EntitySchemaFactory extends AbstractJsonFileFactory implements EntitySchemaFactoryInterface
{
    public function get(string $id): EntitySchema
    {
        return parent::get($id);
    }

    protected function getName(): string
    {
        return 'entity';
    }

    protected function instanciate(array $data): object
    {
        return new EntitySchema($data);
    }

    protected function getDirectory(): string
    {
        return EntityRequester::getEntitySchemaDirectory();
    }
}
