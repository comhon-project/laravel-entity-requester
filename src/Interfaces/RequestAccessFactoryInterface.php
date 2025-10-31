<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\RequestAccess;

interface RequestAccessFactoryInterface
{
    public function get(string $id): RequestAccess;
}
