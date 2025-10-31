<?php

namespace Comhon\EntityRequester\Factories;

use Comhon\EntityRequester\DTOs\RequestAccess;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\RequestAccessFactoryInterface;

class RequestAccessFactory extends AbstractJsonFileFactory implements RequestAccessFactoryInterface
{
    public function get(string $id): RequestAccess
    {
        return parent::get($id);
    }

    protected function getName(): string
    {
        return 'request-access';
    }

    protected function instanciate(array $data): object
    {
        return new RequestAccess($data);
    }

    protected function getDirectory(): string
    {
        return EntityRequester::getRequestAccessDirectory();
    }
}
