<?php

namespace Comhon\EntityRequester\Factories\Schema;

use Comhon\EntityRequester\DTOs\RequestSchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;

class RequestSchemaFactory extends AbstractJsonFileFactory implements RequestSchemaFactoryInterface
{
    public function get(string $id): RequestSchema
    {
        return parent::get($id);
    }

    protected function getName(): string
    {
        return 'request';
    }

    protected function instanciate(array $data): object
    {
        return new RequestSchema($data);
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
        return EntityRequester::getRequestSchemaDirectory();
    }
}
