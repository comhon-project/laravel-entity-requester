<?php

namespace Comhon\EntityRequester\Factories\Schema;

use Comhon\EntityRequester\Exceptions\SchemaNotFoundException;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\CacheableInterface;
use Comhon\EntityRequester\Interfaces\ResponsableInterface;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

abstract class AbstractJsonFileFactory implements CacheableInterface, ResponsableInterface
{
    protected array $collection = [];

    private Repository $cache;

    abstract protected function getName(): string;

    abstract protected function instanciate(array $data): object;

    abstract protected function getDirectory(): string;

    public function get(string $id): object
    {
        $id = $this->resolveId($id);

        if (! isset($this->collection[$id])) {
            $this->register($id);
        }

        return $this->collection[$id];
    }

    public function getJson(string $id): string
    {
        $id = $this->resolveId($id);

        $loader = function () use ($id) {
            try {
                return file_get_contents($this->getPath($id));
            } catch (\Throwable $th) {
                throw new SchemaNotFoundException($this->getName(), $id);
            }
        };

        return EntityRequester::useCache()
            ? $this->getCache()->rememberForever("entity-requester::{$this->getName()}-json::{$id}", $loader)
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
        if ($id !== null) {
            $id = $this->resolveId($id);
            $this->getCache()->forget("entity-requester::{$this->getName()}-object::{$id}");
            $this->getCache()->forget("entity-requester::{$this->getName()}-json::{$id}");
            unset($this->collection[$id]);
        } elseif ($this->getCache() instanceof TaggedCache) {
            $this->getCache()->flush();
            $this->collection = [];
        } else {
            throw new \Exception('cannot flush cache, cache driver must manage tags');
        }
    }

    /**
     * @return Repository|Store
     */
    public function getCache(): Repository
    {
        if (! isset($this->cache)) {
            $this->cache = Cache::getStore() instanceof TaggableStore
                ? Cache::tags(["entity-requester::{$this->getName()}"])
                : Cache::store();
        }

        return $this->cache;
    }

    public function getPath(string $id): string
    {
        return $this->getDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }

    protected function register(string $id): void
    {
        $loader = function () use ($id) {
            $data = json_decode($this->getJson($id), true);

            return $this->instanciate($data);
        };
        $this->collection[$id] = EntityRequester::useCache()
            ? $this->getCache()->rememberForever("entity-requester::{$this->getName()}-object::{$id}", $loader)
            : $loader();
    }

    private function resolveId(string $id): string
    {
        if (! str_contains($id, '\\')) {
            return $id;
        }

        return app(ModelResolverInterface::class)->getUniqueName($id)
            ?? throw new \Exception("model $id doesn't have unique name");
    }
}
