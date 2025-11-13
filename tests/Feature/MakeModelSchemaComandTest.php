<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Comhon\EntityRequester\Commands\MakeModelSchema;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class MakeModelSchemaComandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $schemaDir = base_path('schemas');
        if (file_exists($schemaDir)) {
            File::deleteDirectory($schemaDir);
        }

        $requestDir = base_path('requests');
        if (file_exists($requestDir)) {
            File::deleteDirectory($requestDir);
        }

        config([
            'entity-requester.entity_schema_directory' => $schemaDir,
            'entity-requester.request_schema_directory' => $requestDir,
        ]);
    }

    private function getSchemaPath(string $filename): string
    {
        return EntityRequester::getEntitySchemaDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    private function getRequestSchemaPath(string $filename): string
    {
        return EntityRequester::getRequestSchemaDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    private function getExpectedEntitySchemaPath(string $fileName)
    {
        $filePath = $this->getDataFilePath('schemas/'.$fileName);
        if (! class_exists(Scope::class)) {
            $schema = json_decode(file_get_contents($filePath), true);
            $schema['scopes'] = collect($schema['scopes'])->filter(
                fn ($scope) => ! is_array($scope) || $scope['id'] != 'age'
            )->values();
            $filePath = tempnam(sys_get_temp_dir(), 'schema-');
            file_put_contents($filePath, json_encode($schema, JSON_PRETTY_PRINT));
        }

        return $filePath;
    }

    private function getExpectedRequestSchemaPath(string $fileName)
    {
        $filePath = $this->getDataFilePath('requests/'.$fileName);
        if (! class_exists(Scope::class)) {
            $requestSchema = json_decode(file_get_contents($filePath), true);
            $requestSchema['filtrable']['scopes'] = collect($requestSchema['filtrable']['scopes'])->filter(
                fn ($scopeId) => $scopeId != 'age'
            )->values();
            $filePath = tempnam(sys_get_temp_dir(), 'schema-');
            file_put_contents($filePath, json_encode($requestSchema, JSON_PRETTY_PRINT));
        }

        return $filePath;
    }

    private function getDataFilePath(string $fileName)
    {
        $fileName = str_replace('/', DIRECTORY_SEPARATOR, $fileName);

        return dirname(__DIR__).DIRECTORY_SEPARATOR.'Data'.DIRECTORY_SEPARATOR.$fileName;
    }

    private function toJsonSchemaPart($array, $indentCount): string
    {
        return str_replace(
            PHP_EOL,
            PHP_EOL.str_pad('', $indentCount),
            json_encode($array, JSON_PRETTY_PRINT)
        );
    }

    private function callMakeModelSchemaCommand(string $model, array $options = [], bool $pretty = true)
    {
        if (! isset($options['--filtrable'])) {
            $options['--filtrable'] = 'none';
        }
        if (! isset($options['--sortable'])) {
            $options['--sortable'] = 'none';
        }
        if (! isset($options['--scopable'])) {
            $options['--scopable'] = 'none';
        }
        if ($pretty) {
            $options['--pretty'] = true;
        }
        Artisan::call('entity-requester:make-model-schema', [
            'model' => $model,
            ...$options,
        ]);
    }

    private function copyUserSchemaFiles()
    {
        mkdir(EntityRequester::getEntitySchemaDirectory());
        copy(
            $this->getDataFilePath('schemas/user-partial.json'),
            $this->getSchemaPath('user.json')
        );
        copy(
            $this->getDataFilePath('schemas/user.lock'),
            $this->getSchemaPath('user.lock')
        );

        mkdir(EntityRequester::getRequestSchemaDirectory());
        copy(
            $this->getDataFilePath('requests/user-partial.json'),
            $this->getRequestSchemaPath('user.json')
        );
        copy(
            $this->getDataFilePath('requests/user.lock'),
            $this->getRequestSchemaPath('user.lock')
        );
    }

    public function assertSchemaStringEqualsSchemaString(string $schema1, string $schema2)
    {
        $schemas = [
            json_decode($schema1, true),
            json_decode($schema2, true),
        ];

        // table colums are not always retrieve in the same order
        // so properties order may vary and create a false negative
        // so we reorder all properties by id
        foreach ($schemas as &$schema) {
            if (isset($schema['properties'])) {
                $schema['properties'] = collect($schema['properties'])->sortBy('id')->values()->all();
            }
            if (isset($schema['filtrable']['properties'])) {
                $schema['filtrable']['properties'] = collect($schema['filtrable']['properties'])->sort()->values()->all();
            }
            if (isset($schema['sortable'])) {
                $schema['sortable'] = collect($schema['sortable'])->sort()->values()->all();
            }
        }
        $this->assertEquals($schemas[0], $schemas[1]);
    }

    public function assertSchemaStringEqualsSchemaFile(string $path, string $schema)
    {
        $this->assertSchemaStringEqualsSchemaString(
            file_get_contents($path),
            $schema,
        );
    }

    public function assertSchemaFileEqualsSchemaFile(string $path1, string $path2)
    {
        $this->assertSchemaStringEqualsSchemaString(
            file_get_contents($path1),
            file_get_contents($path2),
        );
    }

    public function test_filtrable_and_sortable_prompting()
    {
        $this->artisan('entity-requester:make-model-schema', ['model' => 'User'])
            ->expectsQuestion('Which properties should be filtrable ?', 'foo')
            ->expectsQuestion('Which properties should be filtrable ?', 'none')
            ->expectsQuestion('Which scope should be filtrable ?', 'foo')
            ->expectsQuestion('Which scope should be filtrable ?', 'none')
            ->expectsQuestion('Which properties should be sortable ?', 'foo')
            ->expectsQuestion('Which properties should be sortable ?', 'none')
            ->assertExitCode(0);
    }

    #[DataProvider('providerBoolean')]
    public function test_create_model_schema_success($pretty)
    {
        $this->callMakeModelSchemaCommand('User', [], $pretty);

        $this->assertFileExists($this->getSchemaPath('user.json'));
        $json = file_get_contents($this->getSchemaPath('user.json'));
        $this->assertEquals($pretty, str_contains($json, "\n"));
        $this->assertSchemaStringEqualsSchemaFile($this->getExpectedEntitySchemaPath('user.json'), $json);

        $this->assertFileExists($this->getRequestSchemaPath('user.json'));
        $json = file_get_contents($this->getRequestSchemaPath('user.json'));
        $this->assertEquals($pretty, str_contains($json, "\n"));
        $this->assertSchemaStringEqualsSchemaFile($this->getExpectedRequestSchemaPath('user.json'), $json);
    }

    public function test_create_model_schema_full_name_space_success()
    {
        $this->callMakeModelSchemaCommand(User::class);
        $this->assertFileExists($this->getSchemaPath('user.json'));
    }

    public function test_create_model_schema_failure_no_public_name()
    {
        $this->expectExceptionMessage('public name is not defined for class App\Models\NoPublicName');
        $this->callMakeModelSchemaCommand('NoPublicName');
    }

    public function test_create_model_schema_failure_model_not_found()
    {
        $this->expectExceptionMessage('model foo not found');
        $this->callMakeModelSchemaCommand('foo');
    }

    public function test_create_model_schema_with_visible_hidden_properties_success()
    {
        $this->callMakeModelSchemaCommand('Visible');

        $this->assertFileExists($this->getSchemaPath('visible.json'));
        $json = file_get_contents($this->getSchemaPath('visible.json'));

        $expected = <<<'EOT'
            {
                "id": "visible",
                "name": "visible",
                "properties": [
                    {
                        "id": "visible",
                        "type": "string",
                        "nullable": false
                    }
                ],
                "unique_identifier": "id",
                "primary_identifiers": null,
                "scopes": []
            }
            EOT;

        $this->assertSchemaStringEqualsSchemaString($expected, $json);
    }

    public function test_create_model_schema_with_morph_to_relation_success()
    {
        $this->callMakeModelSchemaCommand('Purchase');

        $this->assertFileExists($this->getSchemaPath('purchase.json'));
        $json = file_get_contents($this->getSchemaPath('purchase.json'));

        $expected = <<<'EOT'
            {
                "id": "purchase",
                "name": "purchase",
                "properties": [
                    {
                        "id": "id",
                        "type": "integer",
                        "nullable": false
                    },
                    {
                        "id": "amount",
                        "type": "integer",
                        "nullable": false
                    },
                    {
                        "id": "buyer_id",
                        "type": "integer",
                        "nullable": false
                    },
                    {
                        "id": "buyer_type",
                        "type": "string",
                        "nullable": false
                    },
                    {
                        "id": "buyer",
                        "type": "relationship",
                        "relationship_type": "morph_to",
                        "morph_type": "buyer_type",
                        "foreign_key": "buyer_id"
                    },
                    {
                        "id": "tags",
                        "type": "relationship",
                        "relationship_type": "morph_to_many",
                        "model": "tag"
                    }
                ],
                "unique_identifier": "id",
                "primary_identifiers": null,
                "scopes": [
                    {
                        "id": "expensive",
                        "parameters": []
                    }
                ]
            }
            EOT;

        $this->assertSchemaStringEqualsSchemaString($expected, $json);
    }

    #[DataProvider('providerFiltrableOptions')]
    public function test_create_model_schema_filtrable_option($option, $expectedFiltrable)
    {
        $this->callMakeModelSchemaCommand(User::class, ['--filtrable' => $option]);
        $this->assertFileExists($this->getRequestSchemaPath('user.json'));
        $json = file_get_contents($this->getRequestSchemaPath('user.json'));

        $expected = str_replace(
            '"properties": []',
            '"properties": '.$this->toJsonSchemaPart($expectedFiltrable, 12),
            file_get_contents($this->getExpectedRequestSchemaPath('user.json')),
        );

        $this->assertSchemaStringEqualsSchemaString($expected, $json);
    }

    public static function providerFiltrableOptions()
    {
        return [
            [
                'none',
                [],
            ],
            [
                'all',
                [
                    'id',
                    'email',
                    'password',
                    'name',
                    'first_name',
                    'preferred_locale',
                    'birth_date',
                    'birth_day',
                    'birth_hour',
                    'age',
                    'score',
                    'comment',
                    'status',
                    'favorite_fruits',
                    'has_consumer_ability',
                    'email_verified_at',
                    'parent_id',
                    'posts',
                    'friends',
                    'purchases',
                    'childrenPosts',
                ],
            ],
            [
                'attributes',
                [
                    'id',
                    'email',
                    'password',
                    'name',
                    'first_name',
                    'preferred_locale',
                    'birth_date',
                    'birth_day',
                    'birth_hour',
                    'age',
                    'score',
                    'comment',
                    'status',
                    'favorite_fruits',
                    'has_consumer_ability',
                    'email_verified_at',
                    'parent_id',
                ],
            ],
            [
                'model',
                [
                    'id',
                    'name',
                    'posts',
                ],
            ],
        ];
    }

    #[DataProvider('providerSortableOptions')]
    public function test_create_model_schema_sortable_option($option, $expectedSortable)
    {
        $this->callMakeModelSchemaCommand(User::class, ['--sortable' => $option]);
        $this->assertFileExists($this->getRequestSchemaPath('user.json'));
        $json = file_get_contents($this->getRequestSchemaPath('user.json'));

        $expected = str_replace(
            '"sortable": []',
            '"sortable": '.$this->toJsonSchemaPart($expectedSortable, 8),
            file_get_contents($this->getExpectedRequestSchemaPath('user.json')),
        );

        $this->assertSchemaStringEqualsSchemaString($expected, $json);
    }

    public static function providerSortableOptions()
    {
        return [
            [
                'none',
                [],
            ],
            [
                'all',
                [
                    'id',
                    'email',
                    'password',
                    'name',
                    'first_name',
                    'preferred_locale',
                    'birth_date',
                    'birth_day',
                    'birth_hour',
                    'age',
                    'score',
                    'comment',
                    'status',
                    'favorite_fruits',
                    'has_consumer_ability',
                    'email_verified_at',
                    'parent_id',
                    'posts',
                    'friends',
                    'purchases',
                    'childrenPosts',
                ],
            ],
            [
                'attributes',
                [
                    'id',
                    'email',
                    'password',
                    'name',
                    'first_name',
                    'preferred_locale',
                    'birth_date',
                    'birth_day',
                    'birth_hour',
                    'age',
                    'score',
                    'comment',
                    'status',
                    'favorite_fruits',
                    'has_consumer_ability',
                    'email_verified_at',
                    'parent_id',
                ],
            ],
            [
                'model',
                [
                    'id',
                    'name',
                ],
            ],
        ];
    }

    #[DataProvider('providerScopableOptions')]
    public function test_create_model_schema_scopable_option($option, $expectedScopable)
    {
        $this->callMakeModelSchemaCommand(User::class, ['--scopable' => $option]);
        $this->assertFileExists($this->getRequestSchemaPath('user.json'));
        $json = file_get_contents($this->getRequestSchemaPath('user.json'));

        if (! class_exists(Scope::class)) {
            $expectedScopable = collect($expectedScopable)->filter(
                fn ($scopeId) => $scopeId != 'age'
            )->values();
        }

        $expected = str_replace(
            '"scopes": []',
            '"scopes": '.$this->toJsonSchemaPart($expectedScopable, 8),
            file_get_contents($this->getExpectedRequestSchemaPath('user.json')),
        );

        $this->assertSchemaStringEqualsSchemaString($expected, $json);
    }

    public static function providerScopableOptions()
    {
        return [
            [
                'none',
                [],
            ],
            [
                'all',
                [
                    'validated',
                    'age',
                    'bool',
                    'carbon',
                    'dateTime',
                    'foo',
                    'resolvable',
                ],
            ],
            [
                'model',
                [
                    'foo',
                    'bool',
                ],
            ],
        ];
    }

    #[DataProvider('providerBoolean')]
    public function test_update_model_schema_success($fresh)
    {
        $this->copyUserSchemaFiles();
        $this->callMakeModelSchemaCommand(User::class, [
            '--filtrable' => $fresh ? 'none' : 'all',
            '--sortable' => $fresh ? 'none' : 'all',
            '--scopable' => $fresh ? 'none' : 'all',
            ...($fresh ? ['--fresh' => true] : []),
        ]);
        $this->assertFileExists($this->getSchemaPath('user.json'));

        $filename = $fresh ? 'user.json' : 'user-updated-all.json';

        $this->assertSchemaFileEqualsSchemaFile(
            $this->getExpectedEntitySchemaPath($filename),
            $this->getSchemaPath('user.json')
        );

        $this->assertSchemaFileEqualsSchemaFile(
            $this->getExpectedRequestSchemaPath($filename),
            $this->getRequestSchemaPath('user.json')
        );
    }

    public function test_update_model_schema_keep_existing_locked_success()
    {
        $this->copyUserSchemaFiles();
        $this->callMakeModelSchemaCommand(User::class, [
            '--filtrable' => 'model',
            '--sortable' => 'model',
            '--scopable' => 'model',
        ]);
        $this->assertFileExists($this->getSchemaPath('user.json'));

        $filename = 'user-updated-model.json';

        $this->assertSchemaFileEqualsSchemaFile(
            $this->getExpectedEntitySchemaPath($filename),
            $this->getSchemaPath('user.json')
        );
        $this->assertSchemaFileEqualsSchemaFile(
            $this->getExpectedRequestSchemaPath($filename),
            $this->getRequestSchemaPath('user.json')
        );
    }

    public function test_mismatching_schema_id()
    {
        $command = new MakeModelSchema;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('saveFile');
        $method->setAccessible(true);

        $property = $reflection->getProperty('modelResolver');
        $property->setAccessible(true);
        $property->setValue($command, app(ModelResolverInterface::class));

        $this->expectExceptionMessage("mismatching 'id' and model unique name on entity schema");
        $method->invoke($command, new User, ['id' => 'foo'], ['id' => 'user']);
    }

    public function test_mismatching_request_schema_id()
    {
        $command = new MakeModelSchema;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('saveFile');
        $method->setAccessible(true);

        $property = $reflection->getProperty('modelResolver');
        $property->setAccessible(true);
        $property->setValue($command, app(ModelResolverInterface::class));

        $this->expectExceptionMessage("mismatching 'id' and model unique name on request schema");
        $method->invoke($command, new User, ['id' => 'user'], ['id' => 'foo']);
    }

    #[DataProvider('provider_databases_colums_query')]
    public function test_databases_colums_query($databaseDriver, $expectedQuery)
    {
        $command = new MakeModelSchema;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getSelectColumnsQuery');
        $method->setAccessible(true);

        $property = $reflection->getProperty('modelResolver');
        $property->setAccessible(true);
        $property->setValue($command, app(ModelResolverInterface::class));

        $query = $method->invoke($command, 'users', $databaseDriver);
        $this->assertEquals($expectedQuery, $query);
    }

    public static function provider_databases_colums_query()
    {
        $table = 'users';

        return [
            [
                'mysql',
                <<<"EOT"
                    SELECT
                        COLUMN_NAME as name,
                        DATA_TYPE as type,
                        CASE
                            WHEN IS_NULLABLE = 'YES' THEN 0
                            ELSE 1
                        END as notnull
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = DATABASE()
                    EOT
            ],
            [
                'mariadb',
                <<<"EOT"
                    SELECT
                        COLUMN_NAME as name,
                        DATA_TYPE as type,
                        CASE
                            WHEN IS_NULLABLE = 'YES' THEN 0
                            ELSE 1
                        END as notnull
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = DATABASE()
                    EOT
            ],
            [
                'pgsql',
                <<<"EOT"
                    SELECT
                        column_name AS name,
                        data_type AS type, 
                        CASE
                            WHEN is_nullable = 'YES' THEN 0
                            ELSE 1
                        END as notnull
                    FROM information_schema.columns
                    WHERE table_name = '$table'
                    EOT
            ],
            [
                'sqlite',
                "PRAGMA table_info($table)",
            ],
        ];
    }

    #[DataProvider('provider_cast_types')]
    public function test_cast_types($castType, $expect)
    {
        $command = new MakeModelSchema;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getTypeInfosFromCast');
        $method->setAccessible(true);

        $property = $reflection->getProperty('modelResolver');
        $property->setAccessible(true);
        $property->setValue($command, app(ModelResolverInterface::class));

        $typeInfos = $method->invoke($command, $castType);
        $this->assertEquals($expect, $typeInfos);
    }

    public static function provider_cast_types()
    {
        return [
            [AsArrayObject::class, ['type' => 'array']],
            ['array', ['type' => 'array']],
            ['collection', ['type' => 'array']],
            ['date', ['type' => 'date']],
            ['datetime', ['type' => 'datetime']],
            ['immutable_date', ['type' => 'date']],
            ['immutable_datetime', ['type' => 'datetime']],
            ['float', ['type' => 'float']],
            ['double', ['type' => 'float']],
            ['real', ['type' => 'float']],
            ['integer', ['type' => 'integer']],
            ['timestamp', ['type' => 'timestamp']],
        ];
    }

    public function test_invalid_resolver()
    {
        MakeModelSchema::registerColumnTypeResolver(fn () => 'foo');

        $this->expectExceptionMessage('Closure registered through registerColumnTypeResolver must return an array or null');
        $this->callMakeModelSchemaCommand('User', []);
    }
}
