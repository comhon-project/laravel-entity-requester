<?php

namespace Comhon\EntityRequester\Database;

use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\RelationshipCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Enums\RelationshipConditionOperator;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\InvalidSortPropertyException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Comhon\EntityRequester\Schema\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use ReflectionMethod;

class EntityRequestBuilder
{
    public function __construct(private SchemaFactoryInterface $schemaFactory) {}

    /**
     * transform given inputs to query builder
     */
    public static function fromInputs(array $inputs, ?string $modelClass = null): Builder
    {
        return static::fromEntityRequest(new EntityRequest($inputs, $modelClass));
    }

    /**
     * transform given entity request to query builder
     */
    public static function fromEntityRequest(EntityRequest $entityRequest): Builder
    {
        $builder = app(static::class);
        $class = $entityRequest->getModelClass();
        $schema = $builder->schemaFactory->get($class);

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $class::query();
        $builder->addSort($query, $entityRequest->getSort());

        if ($entityRequest->getFilter()) {
            $builder->addFilter($schema, $query, $entityRequest->getFilter());
        }

        return $query;
    }

    private function addSort(Builder $query, ?array $sort = null)
    {
        $class = get_class($query->getModel());
        $schema = $this->schemaFactory->get($class);
        if (! empty($sort)) {
            foreach ($sort as $sortElement) {
                if (strpos($sortElement['property'], '.')) {
                    $this->addRelationshipSort($query, $sortElement);
                } else {
                    if (! $schema->isSortable($sortElement['property'])) {
                        throw new NotSortableException($sortElement['property']);
                    }
                    $query->orderBy($sortElement['property'], $sortElement['order']->value);
                }
            }
        } elseif ($defaultSort = $schema->getDefaultSort()) {
            foreach ($defaultSort as $sort) {
                $query->orderBy($sort['property'], $sort['order'] ?? 'ASC');
            }
        } else {
            $query->orderBy($query->getModel()->getKeyName());
        }
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addFilter(Schema $schema, Builder $query, AbstractCondition $filter, bool $and = true, ?string $table = null)
    {
        match (get_class($filter)) {
            Condition::class => $this->addCondition($schema, $query, $filter, $and, $table),
            Group::class => $this->addGroup($schema, $query, $filter, $and, $table),
            Scope::class => $this->addScope($schema, $query, $filter, $and),
            RelationshipCondition::class => $this->addRelationshipCondition($query, $filter, $and),
        };
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addCondition(
        Schema $schema,
        Builder $query,
        Condition $condition,
        bool $and = true,
        ?string $table = null
    ) {
        $propertyId = $condition->getProperty();
        $value = $condition->getValue();

        if (! $schema->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }

        $table = empty($table) ? $query->getModel()->getTable() : $table;
        $column = "$table.$propertyId";

        if ($and) {
            if ($condition->getOperator() == ConditionOperator::In) {
                $query->whereIn($column, $value);
            } elseif ($condition->getOperator() == ConditionOperator::NotIn) {
                $query->whereNotIn($column, $value);
            } else {
                $query->where($column, $condition->getOperator()->value, $value);
            }
        } else {
            if ($condition->getOperator() == ConditionOperator::In) {
                $query->orWhereIn($column, $value);
            } elseif ($condition->getOperator() == ConditionOperator::NotIn) {
                $query->orWhereNotIn($column, $value);
            } else {
                $query->orWhere($column, $condition->getOperator()->value, $value);
            }
        }
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addGroup(
        Schema $schema,
        Builder $query,
        Group $group,
        bool $and,
        ?string $table = null
    ) {
        $function = function ($subQuery) use ($schema, $group, $table) {
            foreach ($group->getConditions() as $condition) {
                $this->addFilter($schema, $subQuery, $condition, $group->getOperator() == GroupOperator::And, $table);
            }
        };
        if ($and) {
            $query->where($function);
        } else {
            $query->orWhere($function);
        }
    }

    private function addRelationshipCondition(Builder $query, RelationshipCondition $condition, bool $and)
    {
        $schema = $this->schemaFactory->get(get_class($query->getRelation($condition->getProperty())->getRelated()));

        $callWhere = $condition->getOperator() == RelationshipConditionOperator::Has
            ? ($and ? 'whereHas' : 'orWhereHas')
            : ($and ? 'whereDoesntHave' : 'orWhereDoesntHave');

        $callback = $condition->getFilter()
            ? function ($subquery) use ($condition, $schema) {
                $this->addFilter($schema, $subquery, $condition->getFilter());
            }
        : null;

        $query->$callWhere($condition->getProperty(), $callback);
    }

    private function addScope(Schema $schema, Builder $query, Scope $scope, bool $and)
    {
        $scopeName = $scope->getName();
        if (! $schema->isScopable($scopeName)) {
            throw new NotScopableException($scopeName);
        }
        try {
            $scopeParameters = $scope->getParameters() ?? [];
            $schemaScopes = $schema->getScope($scopeName);

            $model = $query->getModel();
            $scopeMethod = 'scope'.ucfirst($scopeName);
            if (! method_exists($model, $scopeMethod)) {
                // tagged method as scope with attribute
                $scopeMethod = $scopeName;
            }
            $reflection = new ReflectionMethod($model, $scopeMethod);
            $methodParameters = $reflection->getParameters();
            array_shift($methodParameters);

            foreach ($schemaScopes['parameters'] ?? [] as $index => $schemaParameter) {
                if (isset($schemaParameter['enum'])) {
                    $type = $methodParameters[$index]->getType()->getName();
                    if (enum_exists($type)) {
                        $scopeParameters[$index] = $type::from($scopeParameters[$index]);
                    }
                }
            }

            $callWhere = $and ? 'where' : 'orWhere';
            $query->$callWhere(function ($subquery) use ($scopeName, $scopeParameters) {
                try {
                    $subquery->$scopeName(...$scopeParameters);
                } catch (\Throwable $th) {
                    throw new InvalidScopeParametersException($scopeName);
                }
            });
        } catch (\Throwable $th) {
            throw new InvalidScopeParametersException($scopeName);
        }
    }

    private function addRelationshipSort(Builder $query, array $relationshipSort)
    {
        $schema = $this->schemaFactory->get(get_class($query->getModel()));
        $explodedProperty = explode('.', $relationshipSort['property']);
        if (count($explodedProperty) != 2) {
            throw new InvalidSortPropertyException($relationshipSort['property']);
        }
        $relationName = $explodedProperty[0];
        $sortProperty = $explodedProperty[1];

        if (! $schema->isSortable($relationName)) {
            throw new NotSortableException($relationName);
        }
        $relation = $query->getRelation($relationName);
        if (! $relation instanceof HasOneOrMany && get_class($relation) !== BelongsTo::class) {
            throw new \Exception('relation not managed : '.get_class($relation));
        }

        $requestedModel = $query->getModel();
        $sortModelClass = get_class($relation->getRelated());
        $foreignSchema = $this->schemaFactory->get($sortModelClass);
        if (! $foreignSchema->isSortable($sortProperty)) {
            throw new NotSortableException($sortProperty);
        }
        if ($relation instanceof HasOneOrMany) {
            $leftOn = $relation->getQualifiedParentKeyName();
            $rightOn = $relation->getQualifiedForeignKeyName();
        } else {
            $leftOn = $relation->getQualifiedForeignKeyName();
            $rightOn = $relation->getQualifiedOwnerKeyName();
        }

        $query->select($requestedModel->getTable().'.*')
            ->leftJoin($relation->getRelated()->getTable(), $leftOn, '=', $rightOn)
            ->where(function ($query) use ($relationshipSort, $foreignSchema, $relation, $rightOn) {
                $query->where(function ($query) use ($relationshipSort, $foreignSchema, $relation) {
                    if ($relation instanceof MorphOneOrMany) {
                        $query->where($relation->getQualifiedMorphType(), $relation->getMorphClass());
                    }
                    if (isset($relationshipSort['filter'])) {
                        $this->addFilter($foreignSchema, $query, $relationshipSort['filter'], true, $relation->getRelated()->getTable());
                    }
                });
                if (($relation instanceof MorphOneOrMany) || isset($relationshipSort['filter'])) {
                    $query->orWhereNull($rightOn);
                }
            })
            ->groupBy($requestedModel->getTable().'.'.$requestedModel->getKeyName())
            ->orderByRaw("MAX({$relation->getRelated()->getTable()}.{$sortProperty}) {$relationshipSort['order']->value}");
    }
}
