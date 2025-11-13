<?php

namespace Comhon\EntityRequester\Factories;

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

    protected function getDirectory(): string
    {
        return EntityRequester::getRequestSchemaDirectory();
    }
}
