<?php

namespace Comhon\EntityRequester\Facades;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Illuminate\Support\Facades\Facade;

/**
 * @method static EntityRequest validate(array $data, ?string $modelClass = null)
 *
 * @see \Comhon\EntityRequester\EntityRequest\EntityRequestValidator
 */
class EntityRequestValidator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Comhon\EntityRequester\EntityRequest\EntityRequestValidator::class;
    }
}
