<?php

namespace Comhon\EntityRequester\Interfaces;

use Comhon\EntityRequester\Schema\Schema;

interface SchemaFactoryInterface
{
    public function get(string $id): Schema;
}
