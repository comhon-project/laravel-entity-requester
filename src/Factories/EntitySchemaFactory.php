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

    protected function register(string $id): void
    {
        parent::register($id);

        foreach ($this->collection[$id]->getEntities() as $name => $localSchema) {
            $this->collection[$id.'.'.$name] = $localSchema;
        }
    }

    public function refresh(?string $id = null): void
    {
        if ($id !== null) {
            foreach ($this->get($id)->getEntities() as $name => $localSchema) {
                parent::refresh($id.'.'.$name);
            }
        }

        parent::refresh($id);
    }

    protected function getDirectory(): string
    {
        return EntityRequester::getEntitySchemaDirectory();
    }
}
