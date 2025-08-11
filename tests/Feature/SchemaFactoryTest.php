<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Comhon\EntityRequester\Exceptions\SchemaNotFoundException;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Comhon\EntityRequester\Schema\Schema;
use Comhon\EntityRequester\Schema\SchemaFactory;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaFactoryTest extends TestCase
{
    private function getSchemaPath(string $id): string
    {
        return EntityRequester::getSchemaDirectory().DIRECTORY_SEPARATOR.$id.'.json';
    }

    private function getSchemaUserCacheKey(bool $json): string
    {
        return 'entity-requester::schema-'.($json ? 'json' : 'object').'::user';
    }

    private function getSchemaCacheKey(string $id, bool $json): string
    {
        return 'entity-requester::schema-'.($json ? 'json' : 'object').'::'.$id;
    }

    private function getTaggedCache(): Repository
    {
        return app(SchemaFactory::class)->getCache();
    }

    private function getDataFilePath(string $fileName)
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.$fileName;
    }

    #[DataProvider('providerBoolean')]
    public function test_get_schema(bool $fromClass)
    {
        $id = $fromClass ? User::class : 'user';
        $schema = app(SchemaFactoryInterface::class)->get($id);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals(
            json_decode(file_get_contents($this->getDataFilePath('user-indexed.json')), true),
            $schema->getData()
        );
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));
    }

    public function test_get_schema_json()
    {
        $schema = app(SchemaFactory::class)->getJson('user');

        $this->assertJsonStringEqualsJsonFile($this->getSchemaPath('user'), $schema);
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));
    }

    public function test_get_schema_with_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        $schema = app(SchemaFactoryInterface::class)->get('user');

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals(
            json_decode(file_get_contents($this->getDataFilePath('user-indexed.json')), true),
            $schema->getData()
        );
        $this->assertTrue($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertTrue($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        // test retrieve schema from cache
        $schema = app(SchemaFactoryInterface::class)->get('user');

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertEquals(
            json_decode(file_get_contents($this->getDataFilePath('user-indexed.json')), true),
            $schema->getData()
        );
    }

    public function test_get_schema_with_json_cache()
    {
        config(['entity-requester.use_cache' => true]);
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        $schema = app(SchemaFactory::class)->getJson('user');

        $this->assertJsonStringEqualsJsonFile($this->getSchemaPath('user'), $schema);
        $this->assertTrue($this->getTaggedCache()->has($this->getSchemaUserCacheKey(true)));
        $this->assertFalse($this->getTaggedCache()->has($this->getSchemaUserCacheKey(false)));

        // test retrieve schema from cache
        $schema = app(SchemaFactory::class)->getJson('user');
        $this->assertJsonStringEqualsJsonFile($this->getSchemaPath('user'), $schema);
    }

    public function test_flush_all_schemas()
    {
        config(['entity-requester.use_cache' => true]);
        app(SchemaFactory::class)->get('user');
        app(SchemaFactory::class)->get('visible');
        $cache = $this->getTaggedCache();

        $this->assertInstanceOf(TaggedCache::class, $cache);

        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', true)));

        EntityRequester::refreshCache();

        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('visible', true)));
    }

    public function test_flush_one_schemas()
    {
        config(['entity-requester.use_cache' => true]);
        app(SchemaFactory::class)->get('user');
        app(SchemaFactory::class)->get('visible');
        $cache = $this->getTaggedCache();

        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', true)));

        EntityRequester::refreshCache('user');

        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', false)));
        $this->assertFalse($cache->has($this->getSchemaCacheKey('user', true)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', false)));
        $this->assertTrue($cache->has($this->getSchemaCacheKey('visible', true)));
    }

    public function test_get_schema_response()
    {
        $response = app(SchemaFactory::class)->response('user', request());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertStringContainsString('"id": "user"', $response->getContent());
    }

    public function test_get_schema_not_found()
    {
        $this->expectException(SchemaNotFoundException::class);
        app(SchemaFactoryInterface::class)->get('foo');
    }

    public function test_get_schema_unique_name_doesnt_exist()
    {
        $this->expectExceptionMessage("model  doesn't have unique name");
        app(SchemaFactoryInterface::class)->get('App\Models\MyModel');
    }
}
