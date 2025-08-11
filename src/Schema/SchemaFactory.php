<?php

namespace Comhon\EntityRequester\Schema;

use Comhon\EntityRequester\Exceptions\SchemaNotFoundException;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\CacheableInterface;
use Comhon\EntityRequester\Interfaces\ResponsableInterface;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SchemaFactory implements CacheableInterface, ResponsableInterface, SchemaFactoryInterface
{
    public function get(string $id): Schema
    {
        $loader = function () use ($id) {
            $schema = json_decode($this->getJson($id), true);

            return new Schema($schema);
        };

        return EntityRequester::useCache()
            ? $this->getCache()->rememberForever('entity-requester::schema-object::'.$id, $loader)
            : $loader();
    }

    public function getJson(string $id): string
    {
        $id = str_contains($id, '\\')
            ? app(ModelResolverInterface::class)->getUniqueName($id)
            : $id;

        if ($id === null) {
            throw new \Exception("model $id doesn't have unique name");
        }

        $loader = function () use ($id) {
            try {
                return file_get_contents($this->getPath($id));
            } catch (\Throwable $th) {
                throw new SchemaNotFoundException($id);
            }
        };

        return EntityRequester::useCache()
            ? $this->getCache()->rememberForever('entity-requester::schema-json::'.$id, $loader)
            : $loader();
    }

    /**
     * @see Comhon\EntityRequester\Interfaces\ResponsableInterface::response()
     */
    public function response(string $id, $request): JsonResponse
    {
        return new JsonResponse($this->getJson($id), json: true);
    }

    /**
     * @see Comhon\EntityRequester\Interfaces\CacheableInterface::refresh()
     */
    public function refresh(?string $id = null): void
    {
        if ($id) {
            $this->getCache()->forget('entity-requester::schema-object::'.$id);
            $this->getCache()->forget('entity-requester::schema-json::'.$id);
        } else {
            $this->getCache()->flush();
        }
    }

    /**
     * @return Repository|\Illuminate\Contracts\Cache\Store
     */
    public function getCache(): Repository
    {
        return Cache::getStore() instanceof TaggableStore
            ? Cache::tags(['entity-requester::schema'])
            : Cache::store();
    }

    private function getPath(string $id): string
    {
        return EntityRequester::getSchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }
}
