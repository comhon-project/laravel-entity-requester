<?php

namespace Comhon\EntityRequester\Commands;

use Carbon\Carbon;
use Comhon\ModelResolverContract\ModelResolverInterface;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;

class MakeModelSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entity-requester:make-model-schema
                            {model}
                            {--filtrable= : properties that should be filtrable (all, attributes, none, model) }
                            {--sortable= : properties that should be sortable (attributes, none, model) }
                            {--pretty : serialize json with whitespace and line breaks }
                            {--fresh : only for existing schemas. if specified, overwrite existing schema with a fresh one, otherwise only apply needed updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate a model schema';

    private ModelResolverInterface $modelResolver;

    /**
     * Execute the console command.
     */
    public function handle(ModelResolverInterface $modelResolver)
    {
        $this->modelResolver = $modelResolver;
        $modelInput = $this->argument('model');
        $fresh = $this->option('fresh');
        $modelClass = null;

        if (str_contains($modelInput, '\\') && class_exists($modelInput)) {
            $modelClass = $modelInput;
        } elseif (class_exists($class = 'App\Models\\'.$modelInput)) {
            $modelClass = $class;
        } elseif (class_exists($class = 'App\\'.$modelInput)) {
            $modelClass = $class;
        }
        if (! $modelClass) {
            throw new \Exception("model $modelInput not found");
        }

        $allowedValues = ['none', 'attributes', 'model', 'all'];
        $filtrable = $this->getRequestOption('filtrable', $allowedValues);

        $allowedValues = ['none', 'attributes', 'model'];
        $sortable = $this->getRequestOption('sortable', $allowedValues);

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass;

        $schema = $this->initSchema($model, $fresh);

        // Locked properties, filters, and sorts are skipped:
        // - If the element is present in the existing schema, it must remain unchanged in the updated schema.
        // - If the element is absent in the existing schema, it must remain absent in the updated schema.
        $lock = $this->initLock($model, $fresh);

        $schema['properties'] = $this->getProperties(
            $model,
            $schema['properties'] ?? [],
            $lock['properties'] ?? [],
        );

        $schema['request']['filtrable']['properties'] = $this->getRequestProperties(
            $model,
            $filtrable,
            $schema['properties'],
            $schema['request']['filtrable']['properties'] ?? [],
            $lock['request']['filtrable']['properties'] ?? [],
            'filtrable',
        );
        $schema['request']['filtrable']['scopes'] = $this->getModelScopes(
            $model,
            $schema['request']['filtrable']['scopes'] ?? [],
            $lock['request']['filtrable']['scopes'] ?? [],
        );
        $schema['request']['sortable'] = $this->getRequestProperties(
            $model,
            $sortable,
            $schema['properties'],
            $schema['request']['sortable'] ?? [],
            $lock['request']['sortable'] ?? [],
            'sortable',
        );

        $this->saveFile($model, $schema);
    }

    private function initSchema(Model $model, bool $fresh)
    {
        $schema = [
            'id' => $this->getModelUniqueName($model),
            'name' => $this->getModelReadableName($model),
            'properties' => [],
            'unique_identifier' => $model->getKeyName(),
            'primary_identifiers' => null, // TODO interface
            'request' => [
                'filtrable' => [
                    'properties' => [],
                    'scopes' => [],
                ],
                'sortable' => [],
            ],
        ];

        $schemaPath = $this->getSchemaPath($model);
        if (! $fresh && file_exists($schemaPath)) {
            $schema = [
                ...$schema,
                ...json_decode(file_get_contents($schemaPath), true),
            ];
        }

        return $schema;
    }

    private function initLock(Model $model, bool $fresh)
    {
        $lock = [
            'properties' => [],
            'request' => [
                'filtrable' => [
                    'properties' => [],
                    'scopes' => [],
                ],
                'sortable' => [],
            ],
        ];

        $lockPath = $this->getSchemaLockPath($model);
        if (! $fresh && file_exists($lockPath)) {
            $lock = [
                ...$lock,
                ...json_decode(file_get_contents($lockPath), true),
            ];
        }

        return $lock;
    }

    private function getTypeInfosFromDatabase($databaseType)
    {
        $columnType = strtolower($databaseType);
        $type = match (true) {
            str_contains($columnType, 'int') => 'integer',
            str_contains($columnType, 'float') => 'float',
            str_contains($columnType, 'double') => 'float',
            str_contains($columnType, 'real') => 'float',
            str_contains($columnType, 'bool') => 'boolean',
            str_contains($columnType, 'char') => 'string',
            str_contains($columnType, 'text') => 'string',
            str_contains($columnType, 'datetime') => 'datetime',
            str_contains($columnType, 'timestamp') => 'datetime',
            str_contains($columnType, 'date') => 'date',
            str_contains($columnType, 'time') => 'time',
            default => null
        };

        return [
            'type' => $type,
        ];
    }

    private function getTypeInfosFromCast($castType)
    {
        $typeInfos = ['type' => null];

        if (str_contains($castType, AsEnumCollection::class)) {
            $typeInfos['type'] = 'array';
            $enumClass = explode(':', $castType)[1];
            $typeInfos['children'] = $this->getTypeInfosFromCast($enumClass);

            return $typeInfos;
        }

        if (enum_exists($castType)) {
            $typeInfos['enum'] = collect($castType::cases())->map(fn ($case) => $case->value)->all();
            $castType = $this->getEnumBackingType($castType);
        }

        $typeInfos['type'] = match (true) {
            $castType == AsStringable::class => 'string',
            $castType == AsArrayObject::class => 'array',
            str_contains($castType, AsCollection::class) => 'array',
            str_contains($castType, 'int') => 'integer',
            str_contains($castType, 'hashed') => 'string',
            default => $castType
        };

        return $typeInfos;
    }

    private function getEnumBackingType(string $enumClass): string
    {
        $reflection = new ReflectionEnum($enumClass);

        return $reflection->isBacked()
            ? $reflection->getBackingType()->getName() : throw new \Exception("enum '$enumClass' must be backed");
    }

    private function getTypeFromFunctionParameterType(\ReflectionParameter $parameter)
    {
        return $parameter->gettype() instanceof \ReflectionNamedType
            ? match ($parameter->gettype()->getName()) {
                'int' => 'integer',
                'bool' => 'boolean',
                Carbon::class => 'datetime',
                DateTime::class => 'datetime',
                default => $parameter->gettype()->getName(),
            }
        : null;
    }

    private function getProperties(Model $model, array $existingProperties, array $lockedProperties)
    {
        $properties = [];
        $lockedExistingProperties = collect($existingProperties)
            ->filter(fn ($property) => in_array($property['id'], $lockedProperties))
            ->keyBy('id');

        $casts = $model->getCasts();
        $columns = DB::select($this->getSelectColumnsQuery($model->getTable()));

        foreach ($columns as $column) {
            if (in_array($column->name, $lockedProperties)) {
                if ($lockedExistingProperties->has($column->name)) {
                    $properties[] = $lockedExistingProperties->pull($column->name);
                }
            } elseif ($this->isVisibleProperty($model, $column->name)) {
                $castType = $casts[$column->name] ?? null;
                $typeInfos = $castType
                    ? $this->getTypeInfosFromCast($castType)
                    : $this->getTypeInfosFromDatabase($column->type);

                if (isset($typeInfos['type'])) {
                    $properties[] = [
                        'id' => $column->name,
                        ...$typeInfos,
                    ];
                }
            }
        }
        array_push(
            $properties,
            ...$lockedExistingProperties->filter(fn ($prop) => $prop['type'] != 'relationship')->values()
        );

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->isDeprecatedMethod($method)) {
                continue;
            }
            if ($method->getNumberOfParameters() === 0) {
                $returnType = $method->getReturnType();
                if ($returnType && is_subclass_of($returnType->getName(), Relation::class)) {
                    $methodName = $method->getName();
                    if (in_array($methodName, $lockedProperties)) {
                        if ($lockedExistingProperties->has($methodName)) {
                            $properties[] = $lockedExistingProperties->pull($methodName);
                        }
                    } elseif ($this->isVisibleProperty($model, $methodName)) {
                        $relation = $model->{$methodName}();
                        $properties[] = [
                            'id' => $methodName,
                            'type' => 'relationship',
                            'relationship_type' => Str::snake((new ReflectionClass($relation))->getShortName()),
                            'model' => $this->getModelUniqueName($relation->getRelated()),
                        ];
                    }
                }
            }
        }

        array_push(
            $properties,
            ...$lockedExistingProperties->filter(fn ($prop) => $prop['type'] == 'relationship')->values()
        );

        return $properties;
    }

    private function getSelectColumnsQuery(string $table): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql' => "SELECT COLUMN_NAME as name, DATA_TYPE as type FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = DATABASE()",
            'mariadb' => "SELECT COLUMN_NAME as name, DATA_TYPE as type FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = DATABASE()",
            'pgsql' => "SELECT column_name AS name, data_type AS type FROM information_schema.columns WHERE table_name = '$table'",
            'sqlite' => "PRAGMA table_info($table)",
            default => throw new \Exception("not supported driver '$driver'")
        };
    }

    private function isVisibleProperty(Model $model, string $property): bool
    {
        return ! in_array($property, $model->getHidden())
            && (empty($model->getVisible()) || in_array($property, $model->getVisible()));
    }

    private function getModelScopes(Model $model, array $existingScopes, array $lockedScopes)
    {
        $scopes = [];
        $lockedExistingScopes = collect($existingScopes)
            ->keyBy(fn ($scope) => is_array($scope) ? $scope['id'] : $scope)
            ->filter(fn ($scope, $key) => in_array($key, $lockedScopes));

        foreach ($this->getScopeMethods($model) as $method) {
            $methodName = $method->getName();
            $scopeName = $model->hasNamedScope($methodName)
                ? $methodName
                : lcfirst(substr($methodName, strlen('scope')));

            if (in_array($scopeName, $lockedScopes)) {
                if ($lockedExistingScopes->has($scopeName)) {
                    $scopes[] = $lockedExistingScopes->pull($scopeName);
                }
            } else {
                $scope = [
                    'id' => $scopeName,
                ];

                $parameters = $method->getParameters();
                array_shift($parameters);
                $useOperator = false;
                foreach ($parameters as $index => $parameter) {
                    if ($parameter->getName() == 'operator') {
                        $useOperator = true;
                        unset($parameters[$index]);
                    }
                }
                // manage scope with at most one value parameter
                if (count($parameters) <= 1) {
                    if (count($parameters) == 1) {
                        $scope['type'] = $this->getTypeFromFunctionParameterType(current($parameters));
                        $scope['use_operator'] = $useOperator;
                    }
                    $scopes[] = $scope;
                }
            }
        }

        array_push($scopes, ...$lockedExistingScopes->values());

        return $scopes;
    }

    private function getScopeMethods(Model $model): array
    {
        $scopeMethods = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->isDeprecatedMethod($method)) {
                continue;
            }
            $methodName = $method->getName();
            $isScope = $model->hasNamedScope($methodName)
                || (strpos($methodName, 'scope') === 0 && strlen($methodName) > strlen('scope'));

            if ($isScope) {
                $scopeMethods[] = $method;
            }
        }

        return $scopeMethods;
    }

    private function getRequestProperties(
        Model $model,
        string $option,
        array $properties,
        array $existingRequestProps,
        array $lockedRequestProps,
        string $attr
    ): array {
        $properties = collect($properties)->keyBy('id');
        $lockedExistingRequestProps = collect($existingRequestProps)
            ->keyBy(fn ($prop) => $prop)
            ->filter(fn ($prop) => in_array($prop, $lockedRequestProps));

        $requestProps = match ($option) {
            'none' => [],
            'model' => is_array($model->{$attr})
                ? $model->{$attr}
                : throw new \Exception("invalid {$attr} in model ".get_class($model)),
            'all' => $properties
                ->pluck('id')
                ->values()
                ->all(),
            'attributes' => $properties
                ->where('type', '!=', 'relationship')
                ->pluck('id')
                ->values()
                ->all(),
        };

        $requestProperties = [];
        foreach ($requestProps as $requestProp) {
            if (in_array($requestProp, $lockedRequestProps)) {
                if ($lockedExistingRequestProps->has($requestProp)) {
                    $requestProperties[] = $lockedExistingRequestProps->pull($requestProp);
                }
            } elseif ($properties->has($requestProp)) {
                $requestProperties[] = $requestProp;
            }
        }

        array_push($requestProperties, ...$lockedExistingRequestProps->values());

        return $requestProperties;
    }

    private function saveFile(Model $model, array $schema)
    {
        if ($this->getModelUniqueName($model) !== $schema['name']) {
            throw new \Exception('mismatching names');
        }

        $schemaPath = $this->getSchemaPath($model);
        $schemaDir = dirname($schemaPath);
        if (! file_exists($schemaDir)) {
            mkdir($schemaDir);
        }
        file_put_contents(
            $schemaPath,
            json_encode($schema, $this->option('pretty') ? JSON_PRETTY_PRINT : 0)
        );
    }

    private function getModelUniqueName(Model $model)
    {
        return $this->modelResolver->getUniqueName(get_class($model))
            ?? throw new \Exception('public name is not defined for class '.get_class($model));
    }

    private function getModelReadableName(Model $model)
    {
        return Str::snake($this->getModelUniqueName($model), ' ');
    }

    private function getSchemaPath(Model $model)
    {
        return $this->getSchemaPathWithoutExtension($model).'.json';
    }

    private function getSchemaLockPath(Model $model)
    {
        return $this->getSchemaPathWithoutExtension($model).'.lock';
    }

    private function getSchemaPathWithoutExtension(Model $model)
    {
        return base_path('schemas').DIRECTORY_SEPARATOR.$this->getModelUniqueName($model);
    }

    private function getRequestOption(string $option, array $allowedValues): string
    {
        $value = $this->option($option);

        while (! in_array($value, $allowedValues)) {
            if ($value) {
                $this->warn("Invalid option for --$option. Allowed values are: ".implode(', ', $allowedValues));
            }

            $value = $this->choice(
                "Which properties should be $option ?",
                $allowedValues,
            );
        }

        return $value;
    }

    private function isDeprecatedMethod(\ReflectionMethod $method): bool
    {
        foreach ($method->getAttributes() as $attribute) {
            if ($attribute->getName() == 'Deprecated') {
                return true;
            }
        }

        return false;
    }
}
