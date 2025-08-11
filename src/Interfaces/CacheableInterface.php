<?php

namespace Comhon\EntityRequester\Interfaces;

interface CacheableInterface
{
    /**
     * @param  ?string  $schemaId  if string given, refresh only given schema.
     *                             otherwise, flush all schema (usable only for TaggableStore).
     */
    public function refresh(?string $id = null): void;
}
