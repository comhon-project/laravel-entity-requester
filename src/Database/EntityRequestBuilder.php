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
use Comhon\EntityRequester\Exceptions\InvalidToManySortException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Exceptions\NotSupportedOperatorException;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
use Comhon\EntityRequester\Schema\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
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
    private function addFilter(
        Schema $schema,
        Builder|JoinClause $query,
        AbstractCondition $filter,
        bool $and = true,
        ?string $table = null
    ): Builder|JoinClause {
        match (get_class($filter)) {
            Condition::class => $this->addCondition($schema, $query, $filter, $and, $table),
            Group::class => $this->addGroup($schema, $query, $filter, $and, $table),
            Scope::class => $this->addScope($schema, $query, $filter, $and),
            RelationshipCondition::class => $this->addRelationshipCondition($schema, $query, $filter, $and, $table),
        };

        return $query;
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addCondition(
        Schema $schema,
        Builder|JoinClause $query,
        Condition $condition,
        bool $and = true,
        ?string $table = null
    ) {
        $propertyId = $condition->getProperty();

        if (! $schema->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }

        if (empty($table)) {
            $table = $query instanceof JoinClause
                ? (str_contains($table = $query->table, ' as ') ? explode(' as ', $table)[1] : $table)
                : $query->getModel()->getTable();
        }

        $column = "$table.$propertyId";
        $value = $condition->getValue();

        if (! $condition->getOperator()->isSupported()) {
            throw new NotSupportedOperatorException($condition->getOperator());
        }

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
        Builder|JoinClause $query,
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

    private function addRelationshipCondition(
        Schema $schema,
        Builder $query,
        RelationshipCondition $condition,
        bool $and,
        ?string $table,
    ) {
        $operator = '>=';
        $count = 1;
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();

        if (! $schema->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }

        $callWhere = $condition->getOperator() == RelationshipConditionOperator::Has
            ? ($and ? 'whereHas' : 'orWhereHas')
            : ($and ? 'whereDoesntHave' : 'orWhereDoesntHave');

        $callback = $filter
            ? function ($subquery, $type = null) use ($query, $propertyId, $filter, $table) {
                if ($table) {
                    $first = explode('.', $subquery->getQuery()->wheres[0]['first']);
                    $subquery->getQuery()->wheres[0]['first'] = $table.".{$first[1]}";
                }
                $relation = $query->getRelation($propertyId);
                $class = $relation instanceof MorphTo
                    ? (Relation::getMorphedModel($type) ?? $type)
                    : get_class($relation->getRelated());

                $schemaProperty = $this->schemaFactory->get($class);
                $this->addFilter($schemaProperty, $subquery, $filter);
            }
        : null;

        $query->$callWhere($propertyId, $callback, $operator, $count);
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
        if ($relation instanceof MorphTo) {
            throw new \Exception('MorphTo relations not managed for sorting');
        }

        $requestedModel = $query->getModel();
        $sortModelClass = get_class($relation->getRelated());
        $foreignSchema = $this->schemaFactory->get($sortModelClass);
        if (! $foreignSchema->isSortable($sortProperty)) {
            throw new NotSortableException($sortProperty);
        }

        $filter = $relationshipSort['filter'] ?? null;

        $joinType = 'left';
        $aliasLeft = null;
        $aliasRight = null;

        // We don’t have control over scopes and relations with conditions,
        // especially when it comes to table aliases and qualified columns.
        // To avoid SQL errors, we use subqueries to isolate conditions
        // that won’t be affected by the rest of the query.
        $needSubquery = $this->hasFilterClass($filter, Scope::class)
            || count($relation->getQuery()->getQuery()->wheres) > 0;

        if ($needSubquery) {
            $eloquentSubQuery = $relation->getQuery();
            if ($relation instanceof MorphToMany) {
                $morphColumn = $relation->getTable().'.'.$relation->getMorphType();
                $joins = $eloquentSubQuery->getQuery()->joins;
                $join = $joins[array_key_last($joins)];
                $join->where($morphColumn, $relation->getMorphClass());
                $eloquentSubQuery->getQuery()->addBinding($join->getBindings(), 'join');
            } elseif ($relation instanceof MorphOneOrMany) {
                $morphColumn = $relation->getRelated()->getTable().'.'.$relation->getMorphType();
                $eloquentSubQuery->where($morphColumn, $relation->getMorphClass());
            }
            if ($filter) {
                $this->addFilter($foreignSchema, $eloquentSubQuery, $filter, true);
            }

            $aliasRight = "alias_sub_{$relation->getRelated()->getTable()}_".(++AliasCounter::$current);
            [$leftOn, $rightOn] = RelationJoiner::getjoinColumns($relation, $aliasLeft, $aliasRight);
            $query->joinSub(
                $eloquentSubQuery,
                $aliasRight,
                function ($join) use ($leftOn, $rightOn) {
                    $join->on($leftOn, $rightOn);
                },
                type: $joinType,
            );
        } else {
            $aliasRight = RelationJoiner::joinRelation($query, $relation, $aliasLeft, null, $joinType);

            if ($filter) {
                $originalQueryModel = $query->getModel();
                $relatedModel = $relation->getRelated();
                $query->setModel($relatedModel);
                if ($this->hasFilterClass($filter, RelationshipCondition::class)) {
                    $this->addFilter($foreignSchema, $query, $filter, true, $aliasRight)
                        ->orWhereNull("{$aliasRight}.{$relatedModel->getKeyName()}");
                } else {
                    $joins = $query->getQuery()->joins;
                    $join = $joins[array_key_last($joins)];
                    $this->addFilter($foreignSchema, $join, $filter, true);
                    $query->getQuery()->addBinding($join->getBindings(), 'where');
                }
                $query->setModel($originalQueryModel);
            }
        }

        $query->select($requestedModel->getTable().'.*');

        $qualifedProperty = "{$aliasRight}.{$sortProperty}";
        $order = $relationshipSort['order']->value;
        $isToOne = $relation instanceof HasOne
            || $relation instanceof BelongsTo
            || $relation instanceof HasOneThrough
            || $relation instanceof MorphOne;

        if ($isToOne) {
            $query->orderBy($qualifedProperty, $order);
        } else {
            if (! isset($relationshipSort['aggregation'])) {
                throw new InvalidToManySortException($relationshipSort['property']);
            }
            $aggregation = $relationshipSort['aggregation']->value;
            $qualifedProperty = DB::getQueryGrammar()->wrap($qualifedProperty);
            $query->groupBy("{$requestedModel->getTable()}.{$requestedModel->getKeyName()}")
                ->orderByRaw("{$aggregation}({$qualifedProperty}) {$order}");
        }
    }

    private function hasFilterClass(?AbstractCondition $filter, $filterClass)
    {
        if ($filter) {
            $stack = [$filter];
            while (! empty($stack)) {
                $element = array_pop($stack);
                if ($element instanceof $filterClass) {
                    return true;
                }
                if ($element instanceof Group) {
                    array_push($stack, $element->getConditions());
                }
            }
        }

        return false;
    }
}
