<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Comhon\EntityRequester\Commands\MakeModelSchema;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
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

        $dir = base_path('schemas');
        if (file_exists($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function getExpectedSchemaPath(string $fileName)
    {
        $filePath = $this->getDataFilePath($fileName);
        if (! class_exists(Scope::class)) {
            $schema = json_decode(file_get_contents($filePath), true);
            $schema['request']['filtrable']['scopes'] = collect($schema['request']['filtrable']['scopes'])->filter(
                fn ($scope) => ! is_array($scope) || $scope['id'] != 'age'
            )->values();
            $filePath = tempnam(sys_get_temp_dir(), 'schema-');
            file_put_contents($filePath, json_encode($schema, JSON_PRETTY_PRINT));
        }

        return $filePath;
    }

    private function getDataFilePath(string $fileName)
    {
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
        if ($pretty) {
            $options['--pretty'] = true;
        }
        Artisan::call('entity-requester:make-model-schema', [
            'model' => $model,
            ...$options,
        ]);
    }

    public function test_filtrable_and_sortable_prompting()
    {
        $this->artisan('entity-requester:make-model-schema', ['model' => 'User'])
            ->expectsQuestion('Which properties should be filtrable ?', 'foo')
            ->expectsQuestion('Which properties should be filtrable ?', 'none')
            ->expectsQuestion('Which properties should be sortable ?', 'foo')
            ->expectsQuestion('Which properties should be sortable ?', 'none')
            ->assertExitCode(0);
    }

    #[DataProvider('providerBoolean')]
    public function test_create_model_schema_success($pretty)
    {
        $this->callMakeModelSchemaCommand('User', [], $pretty);

        $this->assertFileExists(base_path('schemas/user.json'));
        $json = file_get_contents(base_path('schemas/user.json'));
        $this->assertEquals($pretty, str_contains($json, "\n"));

        $this->assertJsonStringEqualsJsonFile($this->getExpectedSchemaPath('user.json'), $json);
    }

    public function test_create_model_schema_full_name_space_success()
    {
        $this->callMakeModelSchemaCommand(User::class);
        $this->assertFileExists(base_path('schemas/user.json'));
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

        $this->assertFileExists(base_path('schemas/visible.json'));
        $json = file_get_contents(base_path('schemas/visible.json'));

        $expected = <<<'EOT'
            {
                "id": "visible",
                "name": "visible",
                "properties": [
                    {
                        "id": "visible",
                        "type": "string"
                    }
                ],
                "unique_identifier": "id",
                "primary_identifiers": null,
                "request": {
                    "filtrable": {
                        "properties": [],
                        "scopes": []
                    },
                    "sortable": []
                }
            }
            EOT;

        $this->assertJsonStringEqualsJsonString($expected, $json);
    }

    #[DataProvider('providerFiltrableOptions')]
    public function test_create_model_schema_filtrable_option($option, $expectedFiltrable)
    {
        $this->callMakeModelSchemaCommand(User::class, ['--filtrable' => $option]);
        $this->assertFileExists(base_path('schemas/user.json'));
        $json = file_get_contents(base_path('schemas/user.json'));

        $expected = str_replace(
            '"properties": []',
            '"properties": '.$this->toJsonSchemaPart($expectedFiltrable, 12),
            file_get_contents($this->getExpectedSchemaPath('user.json')),
        );

        $this->assertJsonStringEqualsJsonString($expected, $json);
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
                    'posts',
                    'friends',
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
        $this->assertFileExists(base_path('schemas/user.json'));
        $json = file_get_contents(base_path('schemas/user.json'));

        $expected = str_replace(
            '"sortable": []',
            '"sortable": '.$this->toJsonSchemaPart($expectedSortable, 8),
            file_get_contents($this->getExpectedSchemaPath('user.json')),
        );

        $this->assertJsonStringEqualsJsonString($expected, $json);
    }

    public static function providerSortableOptions()
    {
        return [
            [
                'none',
                [],
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

    #[DataProvider('providerBoolean')]
    public function test_update_model_schema_success($fresh)
    {
        mkdir(base_path('schemas'));
        copy(
            $this->getDataFilePath('user-partial.json'),
            base_path('schemas/user.json')
        );
        copy(
            $this->getDataFilePath('user.lock'),
            base_path('schemas/user.lock')
        );
        $this->callMakeModelSchemaCommand(User::class, [
            '--filtrable' => $fresh ? 'none' : 'all',
            '--sortable' => $fresh ? 'none' : 'attributes',
            ...($fresh ? ['--fresh' => true] : []),
        ]);
        $this->assertFileExists(base_path('schemas/user.json'));

        $filename = $fresh ? 'user.json' : 'user-updated.json';

        $this->assertJsonFileEqualsJsonFile($this->getExpectedSchemaPath($filename), base_path('schemas/user.json'));
    }

    public function test_mismatching_names()
    {
        $command = new MakeModelSchema;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('saveFile');
        $method->setAccessible(true);

        $property = $reflection->getProperty('modelResolver');
        $property->setAccessible(true);
        $property->setValue($command, app(ModelResolverInterface::class));

        $this->expectExceptionMessage('mismatching names');
        $method->invoke($command, new User, ['name' => 'foo']);
    }
}
