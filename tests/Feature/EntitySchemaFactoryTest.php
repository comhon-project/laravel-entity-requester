<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\Exceptions\SchemaNotFoundException;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Factories\EntitySchemaFactory;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntitySchemaFactoryTest extends TestCase
{
    private function getSchemaPath(string $id): string
    {
        return EntityRequester::getEntitySchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }

    private function getSchemaUserCacheKey(bool $json): string
    {
        return 'entity-requester::entity-'.($json ? 'json' : 'object').'::user';
    }

    private function getSchemaCacheKey(string $id, bool $json): string
    {
        return 'entity-requester::entity-'.($json ? 'json' : 'object').'::'.$id;
    }

    private function getTaggedCache(): Repository
    {
        return app(EntitySchemaFactory::class)->getCache();
    }

    private function getDataFilePath(string $fileName)
    {
        $fileName = str_replace('/', DIRECTORY_SEPARATOR, $fileName);

        return dirname(__DIR__).DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.$fileName;
    }

    #[DataProvider('providerBoolean')]
    public function test_get_schema(bool $fromClass)
    {
        $id = $fromClass ? User::class : 'user';
        $schema = app(EntitySchemaFactoryInterface::class)->get($id);

        $this->assertInstanceOf(EntitySchema::class, $schema);
        $this->assertEquals(
            json_decode(file_get_contents($this->getDataFilePath('schemas/entities/user-indexed.json')), true),
            $schema->getData()
        );
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));
    }

    public function test_get_schema_json()
    {
        $schema = app(EntitySchemaFactory::class)->getJson('user');

        $this->assertJsonStringEqualsJsonFile($this->getSchemaPath('user'), $schema);
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));
    }

    public function test_get_schema_with_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        $schema = app(EntitySchemaFactoryInterface::class)->get('user');

        $this->assertInstanceOf(EntitySchema::class, $schema);
        $this->assertEquals(
            json_decode(file_get_contents($this->getDataFilePath('schemas/entities/user-indexed.json')), true),
            $schema->getData()
        );
        $this->assertTrue($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertTrue($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        // test retrieve schema from cache
        $schema = app(EntitySchemaFactoryInterface::class)->get('user');

        $this->assertInstanceOf(EntitySchema::class, $schema);
        $this->assertEquals(
            json_decode(file_get_contents($this->getDataFilePath('schemas/entities/user-indexed.json')), true),
            $schema->getData()
        );
    }

    public function test_get_schema_with_json_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        $schema = app(EntitySchemaFactory::class)->getJson('user');

        $this->assertJsonStringEqualsJsonFile($this->getSchemaPath('user'), $schema);
        $this->assertTrue($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        // test retrieve schema from cache
        $schema = app(EntitySchemaFactory::class)->getJson('user');
        $this->assertJsonStringEqualsJsonFile($this->getSchemaPath('user'), $schema);
    }

    public function test_flush_all_schemas_success()
    {
        config(['entity-requester.use_cache' => true]);
        app(EntitySchemaFactory::class)->get('user');
        app(EntitySchemaFactory::class)->get('visible');
        $cache = $this->getTaggedCache();

        $this->assertInstanceOf(TaggedCache::class, $cache);

        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', true)));

        EntityRequester::refreshEntityCache();

        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('visible', true)));
    }

    public function test_flush_all_schemas_invalid_driver()
    {
        config(['cache.default' => 'database']);
        config(['entity-requester.use_cache' => true]);

        $this->expectExceptionMessage('cache driver must manage tags');
        EntityRequester::refreshEntityCache();
    }

    public function test_flush_one_schemas()
    {
        config(['entity-requester.use_cache' => true]);
        app(EntitySchemaFactory::class)->get('user');
        app(EntitySchemaFactory::class)->get('visible');
        $cache = $this->getTaggedCache();

        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', true)));

        EntityRequester::refreshEntityCache('user');

        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', true)));
    }

    public function test_get_schema_response()
    {
        $response = app(EntitySchemaFactory::class)->response('user', request());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertStringContainsString('"id": "user"', $response->getContent());
    }

    public function test_get_schema_not_found()
    {
        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage("entity 'foo' not found");
        app(EntitySchemaFactoryInterface::class)->get('foo');
    }

    public function test_get_schema_unique_name_doesnt_exist()
    {
        $this->expectExceptionMessage("model  doesn't have unique name");
        app(EntitySchemaFactoryInterface::class)->get('App\Models\MyModel');
    }
}
