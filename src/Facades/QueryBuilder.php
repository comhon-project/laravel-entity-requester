<?php

namespace Comhon\EntityRequester\Facades;

use Comhon\EntityRequester\DTOs\EntityRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Builder fromInputs(array $inputs, ?string $modelClass = null)
 * @method static Builder fromEntityRequest(EntityRequest $entityRequest)
 *
 * @see \Comhon\EntityRequester\EntityRequest\QueryBuilder
 */
class QueryBuilder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Comhon\EntityRequester\EntityRequest\QueryBuilder::class;
    }
}
