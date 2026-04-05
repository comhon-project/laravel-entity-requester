<?php

namespace Comhon\EntityRequester\Facades;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\EntityRequest\Validator;
use Illuminate\Support\Facades\Facade;

/**
 * @method static EntityRequest validate(array $data, ?string $modelClass = null)
 *
 * @see Validator
 */
class EntityRequestValidator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Validator::class;
    }
}
