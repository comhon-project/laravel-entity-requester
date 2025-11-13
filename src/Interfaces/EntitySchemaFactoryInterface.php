<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\EntitySchema;

interface EntitySchemaFactoryInterface
{
    public function get(string $id): EntitySchema;
}
