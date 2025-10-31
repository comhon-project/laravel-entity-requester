<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\DTOs\Schema;

interface SchemaFactoryInterface
{
    public function get(string $id): Schema;
}
