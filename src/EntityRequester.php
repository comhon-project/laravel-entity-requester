<?php

namespace Comhon\EntityRequester;

use Comhon\EntityRequester\Interfaces\CacheableInterface;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;

class EntityRequester
{
    public function __construct(private SchemaFactoryInterface $schemaFactory) {}

    public function getSchemaDirectory(): string
    {
        return config('entity-requester.schema_directory') ?? base_path('schemas');
    }

    public function useCache(): bool
    {
        return config('entity-requester.use_cache') ?? false;
    }

    /**
     * @param  ?string  $schemaId  if string given, refresh only given schema.
     *                             otherwise, flush all schema (usable only for TaggableStore).
     */
    public function refreshCache(?string $schemaId = null): void
    {
        $this->schemaFactory instanceof CacheableInterface
            ? $this->schemaFactory->refresh($schemaId)
            : throw new \Exception('SchemaFactory must be instance of CacheableInterface to refresh cache');

    }
}
