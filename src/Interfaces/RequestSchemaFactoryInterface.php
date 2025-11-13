<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\RequestSchema;

interface RequestSchemaFactoryInterface
{
    public function get(string $id): RequestSchema;
}
