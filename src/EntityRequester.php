<?php

namespace Comhon\EntityRequester;

use Comhon\EntityRequester\Interfaces\CacheableInterface;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;

class EntityRequester
{
    public function getEntitySchemaDirectory(): string
    {
        return config('entity-requester.entity_schema_directory')
            ?? base_path('schemas'.DIRECTORY_SEPARATOR).'entities';
    }

    public function getRequestSchemaDirectory(): string
    {
        return config('entity-requester.request_schema_directory')
        ?? base_path('schemas'.DIRECTORY_SEPARATOR).'requests';
    }

    public function getEnumSchemaDirectory(): string
    {
        return config('entity-requester.enum_schema_directory')
        ?? base_path('schemas'.DIRECTORY_SEPARATOR).'enums';
    }

    public function useCache(): bool
    {
        return config('entity-requester.use_cache') ?? false;
    }

    public function refreshCache(): void
    {
        $this->refreshEntityCache();
        $this->refreshRequestCache();
    }

    /**
     * @param  ?string  $schemaId  if string given, refresh only given entity schema.
     *                             otherwise, flush all entity schemas (usable only for TaggableStore).
     */
    public function refreshEntityCache(?string $schemaId = null): void
    {
        $factory = app(EntitySchemaFactoryInterface::class);
        $factory instanceof CacheableInterface
            ? $factory->refresh($schemaId)
            : throw new \Exception('Entity schema factory must be instance of CacheableInterface to refresh cache');
    }

    /**
     * @param  ?string  $schemaId  if string given, refresh only given request schema.
     *                             otherwise, flush all request schemas (usable only for TaggableStore).
     */
    public function refreshRequestCache(?string $schemaId = null): void
    {
        $factory = app(RequestSchemaFactoryInterface::class);
        $factory instanceof CacheableInterface
            ? $factory->refresh($schemaId)
            : throw new \Exception('Request schema factory must be instance of CacheableInterface to refresh cache');
    }
}
