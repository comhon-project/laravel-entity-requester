<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\EnumSchema;

interface EnumSchemaFactoryInterface
{
    public function get(string $id): EnumSchema;
}
