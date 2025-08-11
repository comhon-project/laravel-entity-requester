<?php

namespace Comhon\EntityRequester\Commands;

use Carbon\Carbon;
use Comhon\EntityRequester\Facades\EntityRequester;
use Comhon\ModelResolverContract\ModelResolverInterface;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;

class MakeModelSchema extends Command
{
    private static ?\Closure $columnTypeResolver = null;

    private static ?\Closure $castTypeResolver = null;

    private static ?\Closure $paramTypeResolver = null;

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

    public static function registerColumnTypeResolver(\Closure $reslover)
    {
        static::$columnTypeResolver = static::getResolverClosure($reslover, __FUNCTION__);
    }

    public static function registerCastTypeResolver(\Closure $reslover)
    {
        static::$castTypeResolver = static::getResolverClosure($reslover, __FUNCTION__);
    }

    public static function registerParamTypeResolver(\Closure $reslover)
    {
        static::$paramTypeResolver = static::getResolverClosure($reslover, __FUNCTION__);
    }

    public static function getResolverClosure(\Closure $reslover, string $function)
    {
        return function (...$params) use ($reslover, $function) {
            $return = $reslover(...$params);

            if ($return !== null && ! is_array($return)) {
                throw new \Exception("Closure registered through $function must return an array or null");
            }

            return $return;
        };
    }

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
            'primary_identifiers' => $model->primaryIdentifiers ?? null,
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
        $typeInfos = ['type' => null];
        $columnType = strtolower($databaseType);

        $typeInfos['type'] = match (true) {
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
            str_contains($columnType, 'int') => 'integer',
            default => null
        };

        if (! isset($typeInfos['type']) && static::$columnTypeResolver) {
            $typeInfos = (static::$columnTypeResolver)($columnType) ?? $typeInfos;
        }

        return $typeInfos;
    }

    private function getTypeInfosFromCast($castType): array
    {
        $typeInfos = ['type' => null];

        if (str_contains($castType, AsEnumCollection::class)) {
            $typeInfos['type'] = 'array';
            $enumClass = explode(':', $castType)[1];
            $typeInfos['children'] = $this->getTypeInfosFromCast($enumClass);

            return $typeInfos;
        }

        if (enum_exists($castType)) {
            $typeInfos['enum'] = collect($castType::cases())
                ->mapWithKeys(fn ($case) => [$case->value => Str::snake($case->name, ' ')])
                ->all();
            $castType = $this->getEnumBackingType($castType);
        }

        $typeInfos['type'] = match ($castType) {
            AsArrayObject::class => 'array',
            'array' => 'array',
            'collection' => 'array',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            'immutable_date' => 'date',
            'immutable_datetime' => 'datetime',
            'float' => 'float',
            'double' => 'float',
            'real' => 'float',
            'int' => 'integer',
            'integer' => 'integer',
            'timestamp' => 'timestamp',
            'string' => 'string',
            AsStringable::class => 'string',
            default => null
        };

        if (! isset($typeInfos['type'])) {
            $typeInfos['type'] = match (true) {
                str_contains($castType, AsCollection::class) => 'array',
                default => null
            };
        }

        if (! isset($typeInfos['type']) && static::$castTypeResolver) {
            $typeInfos = (static::$castTypeResolver)($castType) ?? $typeInfos;
        }

        return $typeInfos;
    }

    private function getTypeFromFunctionParameterType(\ReflectionParameter $parameter): array
    {
        $typeInfos = ['type' => null];

        if (! $parameter->getType() instanceof \ReflectionNamedType) {
            return $typeInfos;
        }

        $paramType = $parameter->getType()->getName();

        if (enum_exists($paramType)) {
            $typeInfos['enum'] = collect($paramType::cases())
                ->mapWithKeys(fn ($case) => [$case->value => Str::snake($case->name, ' ')])
                ->all();
            $paramType = $this->getEnumBackingType($paramType);
        }

        $typeInfos['type'] = match ($paramType) {
            'int' => 'integer',
            'float' => 'float',
            'bool' => 'boolean',
            'string' => 'string',
            Carbon::class => 'datetime',
            DateTime::class => 'datetime',
            default => null,
        };

        if (! isset($typeInfos['type']) && static::$paramTypeResolver) {
            $typeInfos = (static::$paramTypeResolver)($parameter) ?? $typeInfos;
        }

        return $typeInfos;
    }

    private function getEnumBackingType(string $enumClass): string
    {
        $reflection = new ReflectionEnum($enumClass);

        return $reflection->isBacked()
            ? $reflection->getBackingType()->getName()
            : throw new \Exception("enum '$enumClass' must be backed");
    }

    private function getProperties(Model $model, array $existingProperties, array $lockedProperties)
    {
        $properties = [];
        $lockedExistingProperties = collect($existingProperties)
            ->filter(fn ($property) => in_array($property['id'], $lockedProperties))
            ->keyBy('id');

        $casts = $model->getCasts();
        $columns = DB::select($this->getSelectColumnsQuery(
            $model->getTable(),
            DB::connection()->getDriverName()
        ));

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
                        'nullable' => ! $column->notnull,
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
                        $property = [
                            'id' => $methodName,
                            'type' => 'relationship',
                            'relationship_type' => Str::snake((new ReflectionClass($relation))->getShortName()),
                        ];
                        if ($relation instanceof MorphTo) {
                            $property['morph_type'] = $relation->getMorphType();
                        } else {
                            $property['model'] = $this->getModelUniqueName($relation->getRelated());
                        }
                        if ($relation instanceof BelongsTo) {
                            $property['foreign_key'] = $relation->getForeignKeyName();
                        }
                        $properties[] = $property;
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

    private function getSelectColumnsQuery(string $table, string $driver): string
    {
        return match ($driver) {
            'mysql' => <<<"EOT"
                SELECT
                    COLUMN_NAME as name,
                    DATA_TYPE as type,
                    CASE
                        WHEN IS_NULLABLE = 'YES' THEN 0
                        ELSE 1
                    END as notnull
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = DATABASE()
                EOT,
            'mariadb' => <<<"EOT"
                SELECT
                    COLUMN_NAME as name,
                    DATA_TYPE as type,
                    CASE
                        WHEN IS_NULLABLE = 'YES' THEN 0
                        ELSE 1
                    END as notnull
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = '$table' AND TABLE_SCHEMA = DATABASE()
                EOT,
            'pgsql' => <<<"EOT"
                SELECT
                    column_name AS name,
                    data_type AS type, 
                    CASE
                        WHEN is_nullable = 'YES' THEN 0
                        ELSE 1
                    END as notnull
                FROM information_schema.columns
                WHERE table_name = '$table'
                EOT,
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
                $usable = true;
                $parameters = [];
                $methodParameters = $method->getParameters();
                array_shift($methodParameters); // remove query builder parameter
                foreach ($methodParameters as $methodParameter) {
                    $typeInfos = $this->getTypeFromFunctionParameterType($methodParameter);
                    if (! isset($typeInfos['type'])) {
                        $usable = false;
                        break;
                    }
                    $parameters[] = [
                        'id' => $methodParameter->getName(),
                        'name' => Str::snake($methodParameter->getName(), ' '),
                        ...$typeInfos,
                        'nullable' => $methodParameter->allowsNull(),
                    ];
                }

                if ($usable) {
                    $scopes[] = [
                        'id' => $scopeName,
                        'parameters' => $parameters,
                    ];
                }
            }
        }

        array_push($scopes, ...$lockedExistingScopes->values());

        return $scopes;
    }

    /**
     * @return ReflectionMethod[]
     */
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
        return EntityRequester::getSchemaDirectory().DIRECTORY_SEPARATOR.$this->getModelUniqueName($model);
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
