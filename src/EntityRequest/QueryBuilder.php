<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\Database\RelationJoiner;
use Comhon\EntityRequester\Database\Utils;
use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\MorphCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Exceptions\InvalidEntityConditionException;
use Comhon\EntityRequester\Exceptions\InvalidOperatorForPropertyTypeException;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\InvalidToManySortException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Exceptions\NotSupportedOperatorException;
use Comhon\EntityRequester\Exceptions\PropertyNotFoundException;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

class QueryBuilder
{
    public function __construct(
        private EntitySchemaFactoryInterface $entitySchemaFactory,
        private ConditionOperatorManagerInterface $operatorManager,
    ) {}

    /**
     * transform given inputs to query builder
     */
    public function fromInputs(array $inputs, ?string $modelClass = null): Builder
    {
        return static::fromEntityRequest(new EntityRequest($inputs, $modelClass));
    }

    /**
     * transform given entity request to query builder
     */
    public function fromEntityRequest(EntityRequest $entityRequest): Builder
    {
        $builder = app(static::class);
        $class = $entityRequest->getModelClass();
        $schema = $builder->entitySchemaFactory->get($class);

        /** @var Builder $query */
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
        $schema = $this->entitySchemaFactory->get($class);
        if (! empty($sort)) {
            foreach ($sort as $sortElement) {
                $property = $sortElement['property'];

                if (str_contains($property, '.')) {
                    $segments = explode('.', $property);
                    $rootPropertyId = $segments[0];
                    $rootProperty = $schema->getProperty($rootPropertyId);

                    if (! $rootProperty) {
                        throw new PropertyNotFoundException($rootPropertyId, $schema->getId());
                    }

                    if ($rootProperty['type'] === 'object') {
                        $query->orderBy(implode('->', $segments), $sortElement['order']->value);
                    } elseif ($rootProperty['type'] === 'relationship') {
                        $this->addRelationshipSort($query, $sortElement);
                    } else {
                        throw new NotSortableException($rootPropertyId);
                    }
                } else {
                    $query->orderBy($property, $sortElement['order']->value);
                }
            }
        } elseif ($defaultSort = $schema->getDefaultSort()) {
            foreach ($defaultSort as $sort) {
                $query->orderBy($sort['property'], $sort['order'] ?? 'asc');
            }
        } else {
            $query->orderBy($query->getModel()->getKeyName());
        }
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addFilter(
        EntitySchema $schema,
        Builder|JoinClause $query,
        AbstractCondition $filter,
        bool $and = true,
        ?string $table = null,
        ?string $jsonPathPrefix = null,
    ): Builder|JoinClause {
        match (get_class($filter)) {
            Condition::class => $this->addCondition($schema, $query, $filter, $and, $table, $jsonPathPrefix),
            Group::class => $this->addGroup($schema, $query, $filter, $and, $table, $jsonPathPrefix),
            EntityCondition::class, MorphCondition::class => $this->addEntityCondition($schema, $query, $filter, $and, $table, $jsonPathPrefix),
            Scope::class => $jsonPathPrefix !== null
                ? throw new InvalidEntityConditionException(
                    'Scopes are not supported inside object entity conditions'
                )
                : $this->addScope($schema, $query, $filter, $and),
        };

        return $query;
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addCondition(
        EntitySchema $schema,
        Builder|JoinClause $query,
        Condition $condition,
        bool $and = true,
        ?string $table = null,
        ?string $jsonPathPrefix = null,
    ) {
        $propertyId = $condition->getProperty();

        if (empty($table)) {
            $table = $query instanceof JoinClause
                ? (str_contains($table = $query->table, ' as ') ? explode(' as ', $table)[1] : $table)
                : $query->getModel()->getTable();
        }

        $value = $condition->getValue();
        $operator = $condition->getOperator();

        $column = $jsonPathPrefix
            ? "$table.{$jsonPathPrefix}->{$propertyId}"
            : "$table.{$propertyId}";
        $property = $schema->getProperty($propertyId);
        if (! $property) {
            throw new PropertyNotFoundException($propertyId, $schema->getId());
        }
        $propertyType = $property['type'];

        $allowedOperators = $this->operatorManager->getOperatorsForPropertyType($propertyType);

        if (! in_array($operator, $allowedOperators)) {
            throw new InvalidOperatorForPropertyTypeException($operator, $propertyType, $allowedOperators);
        }

        if (! $this->operatorManager->isSupported($operator)) {
            throw new NotSupportedOperatorException($operator);
        }

        if ($and) {
            if ($operator === ConditionOperator::In) {
                $query->whereIn($column, $value);
            } elseif ($operator === ConditionOperator::NotIn) {
                $query->whereNotIn($column, $value);
            } elseif ($operator === ConditionOperator::Contains) {
                $query->whereJsonContains($column, $value);
            } elseif ($operator === ConditionOperator::NotContains) {
                $query->whereJsonDoesntContain($column, $value);
            } elseif ($operator === ConditionOperator::HasKey) {
                $query->whereJsonContainsKey($column.'->'.$value);
            } elseif ($operator === ConditionOperator::HasNotKey) {
                $query->whereJsonDoesntContainKey($column.'->'.$value);
            } else {
                $query->where($column, $this->operatorManager->getSqlOperator($operator), $value);
            }
        } else {
            if ($operator === ConditionOperator::In) {
                $query->orWhereIn($column, $value);
            } elseif ($operator === ConditionOperator::NotIn) {
                $query->orWhereNotIn($column, $value);
            } elseif ($operator === ConditionOperator::Contains) {
                $query->orWhereJsonContains($column, $value);
            } elseif ($operator === ConditionOperator::NotContains) {
                $query->orWhereJsonDoesntContain($column, $value);
            } elseif ($operator === ConditionOperator::HasKey) {
                $query->orWhereJsonContainsKey($column.'->'.$value);
            } elseif ($operator === ConditionOperator::HasNotKey) {
                $query->orWhereJsonDoesntContainKey($column.'->'.$value);
            } else {
                $query->orWhere($column, $this->operatorManager->getSqlOperator($operator), $value);
            }
        }
    }

    /**
     * @param  string  $table  if specified, column name will be prefixed by given table name
     */
    private function addGroup(
        EntitySchema $schema,
        Builder|JoinClause $query,
        Group $group,
        bool $and,
        ?string $table = null,
        ?string $jsonPathPrefix = null,
    ) {
        $function = function ($subQuery) use ($schema, $group, $table, $jsonPathPrefix) {
            foreach ($group->getConditions() as $condition) {
                $this->addFilter($schema, $subQuery, $condition, $group->getOperator() == GroupOperator::And, $table, $jsonPathPrefix);
            }
        };
        if ($and) {
            $query->where($function);
        } else {
            $query->orWhere($function);
        }
    }

    private function addEntityCondition(
        EntitySchema $schema,
        Builder $query,
        EntityCondition $condition,
        bool $and,
        ?string $table,
        ?string $jsonPathPrefix = null,
    ) {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();
        $property = $schema->getProperty($propertyId);
        if (! $property) {
            throw new PropertyNotFoundException($propertyId, $schema->getId());
        }

        if ($condition instanceof MorphCondition) {
            $this->addMorphEntityCondition($query, $condition, $and);
        } elseif ($property['type'] === 'object') {
            $this->addObjectEntityCondition($schema, $query, $condition, $and, $table, $jsonPathPrefix);
        } elseif ($property['type'] === 'relationship') {
            $this->addRelationshipEntityCondition($schema, $query, $condition, $and, $table);
        } else {
            throw new NotFiltrableException($propertyId);
        }
    }

    private function addObjectEntityCondition(
        EntitySchema $schema,
        Builder $query,
        EntityCondition $condition,
        bool $and,
        ?string $table,
        ?string $jsonPathPrefix = null,
    ) {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();
        $isHas = $condition->getOperator() === EntityConditionOperator::Has;

        if (! $isHas && $filter) {
            throw new InvalidEntityConditionException(
                "Operator 'has_not' with filter is not supported on object property '$propertyId'"
            );
        }
        if ($condition->getCountOperator() !== null || $condition->getCount() !== null) {
            throw new InvalidEntityConditionException(
                "Options 'count_operator' and 'count' are not supported on object property '$propertyId'"
            );
        }

        if (empty($table)) {
            $table = $query->getModel()->getTable();
        }

        $columnPath = $jsonPathPrefix
            ? "{$jsonPathPrefix}->{$propertyId}"
            : $propertyId;

        if (! $filter) {
            $column = "$table.{$columnPath}";
            if ($isHas) {
                $and ? $query->whereNotNull($column) : $query->orWhereNotNull($column);
            } else {
                $and ? $query->whereNull($column) : $query->orWhereNull($column);
            }

            return;
        }

        $entityId = $schema->getProperty($propertyId)['entity'];
        $childSchema = $this->entitySchemaFactory->get($entityId);

        $this->addFilter($childSchema, $query, $filter, $and, $table, $columnPath);
    }

    private function addMorphEntityCondition(
        Builder $query,
        MorphCondition $condition,
        bool $and,
    ) {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();
        $isHas = $condition->getOperator() == EntityConditionOperator::Has;
        $countOperator = $condition->getCountOperator()->value ?? '>=';
        $count = $condition->getCount() ?? 1;

        $callWhere = $isHas
            ? ($and ? 'whereHasMorph' : 'orWhereHasMorph')
            : ($and ? 'whereDoesntHaveMorph' : 'orWhereDoesntHaveMorph');

        $callback = $filter
            ? function ($subquery, $type) use ($filter) {
                $schemaProperty = $this->entitySchemaFactory->get($type);
                $this->addFilter($schemaProperty, $subquery, $filter);
            }
        : null;

        $isHas
            ? $query->$callWhere($propertyId, $condition->getEntities(), $callback, $countOperator, $count)
            : $query->$callWhere($propertyId, $condition->getEntities(), $callback);
    }

    private function addRelationshipEntityCondition(
        EntitySchema $schema,
        Builder $query,
        EntityCondition $condition,
        bool $and,
        ?string $table,
    ) {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();
        $isHas = $condition->getOperator() == EntityConditionOperator::Has;
        $countOperator = $condition->getCountOperator()->value ?? '>=';
        $count = $condition->getCount() ?? 1;

        $callWhere = $isHas
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

                $schemaProperty = $this->entitySchemaFactory->get($class);
                $this->addFilter($schemaProperty, $subquery, $filter);
            }
        : null;

        $isHas
            ? $query->$callWhere($propertyId, $callback, $countOperator, $count)
            : $query->$callWhere($propertyId, $callback);
    }

    private function addScope(EntitySchema $schema, Builder $query, Scope $scope, bool $and)
    {
        $scopeName = $scope->getName();
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
        $explodedProperty = explode('.', $relationshipSort['property']);
        $parentModel = $query->getModel();
        $joinType = 'left';
        $aliasLeft = null;
        $relation = null;
        $isToOne = true;
        $filter = $relationshipSort['filter'] ?? null;

        for ($i = 0; $i < count($explodedProperty) - 1; $i++) {
            $segmentName = $explodedProperty[$i];
            $schema = $this->entitySchemaFactory->get(get_class($parentModel));
            $segmentProperty = $schema->getProperty($segmentName);

            if (! $segmentProperty) {
                throw new PropertyNotFoundException($segmentName, $schema->getId());
            }

            if ($segmentProperty['type'] === 'object') {
                break;
            }

            $isLastModel = $i == count($explodedProperty) - 2;
            $currentFilter = $isLastModel ? $filter : null;

            $relation = $parentModel->query()->getRelation($segmentName);
            if ($relation instanceof MorphTo) {
                throw new \Exception('MorphTo relations not managed for sorting');
            }

            if ($isToOne && ! ($relation instanceof HasOne
                || $relation instanceof BelongsTo
                || $relation instanceof HasOneThrough
                || $relation instanceof MorphOne)) {
                $isToOne = false;
            }

            // We don't have control over scopes and relations with conditions,
            // especially when it comes to table aliases and qualified columns.
            // To avoid SQL errors, we use subqueries to isolate conditions
            // that won't be affected by the rest of the query.
            $needSubquery = ($currentFilter && $this->hasFilterClass($currentFilter, Scope::class))
                || count($relation->getQuery()->getQuery()->wheres) > 0;

            $aliasRight = $needSubquery
                ? $this->addJoinSub($query, $relation, $joinType, $aliasLeft, $currentFilter)
                : $this->addJoin($query, $relation, $joinType, $aliasLeft, $currentFilter);

            $parentModel = $relation->getRelated();
            $joinType = 'inner';
            $aliasLeft = $aliasRight;
        }

        $sortProperty = implode('->', array_slice($explodedProperty, $i));

        $requestedModel = $query->getModel();
        $query->select($requestedModel->getTable().'.*');

        $qualifedProperty = "{$aliasRight}.{$sortProperty}";
        $order = $relationshipSort['order']->value;

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

    private function addJoinSub(
        Builder $query,
        $relation,
        string $joinType = 'inner',
        ?string $aliasLeft = null,
        ?AbstractCondition $filter = null,
    ): string {
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

        $aliasRight = Utils::generateAlias("sub_{$relation->getRelated()->getTable()}");
        [$leftOn, $rightOnSub] = RelationJoiner::getjoinColumns($relation, $aliasLeft);

        if ($relation instanceof BelongsToMany || $relation instanceof HasOneOrManyThrough) {
            $aliasColumn = Utils::generateAlias(explode('.', $rightOnSub)[1]);
            $eloquentSubQuery->select(
                $relation->getRelated()->getTable().'.*',
                "$rightOnSub as $aliasColumn",
            );
            $rightOn = Utils::qualify($aliasColumn, $aliasRight);
        } else {
            $rightOn = Utils::qualify($rightOnSub, $aliasRight);
        }

        // the filter must be set on the subquery before doing the join
        // otherwise it is not taken in account
        if ($filter) {
            $foreignSchema = $this->entitySchemaFactory->get(get_class($relation->getRelated()));
            $this->addFilter($foreignSchema, $eloquentSubQuery, $filter);
        }

        $query->joinSub(
            $eloquentSubQuery,
            $aliasRight,
            function ($join) use ($leftOn, $rightOn) {
                $join->on($leftOn, $rightOn);
            },
            type: $joinType,
        );

        return $aliasRight;
    }

    private function addJoin(
        Builder $query,
        $relation,
        string $joinType = 'inner',
        ?string $aliasLeft = null,
        ?AbstractCondition $filter = null,
    ): string {
        $aliasRight = RelationJoiner::joinRelation($query, $relation, $joinType, $aliasLeft);

        if ($filter) {
            $relatedModel = $relation->getRelated();
            $foreignSchema = $this->entitySchemaFactory->get(get_class($relatedModel));
            $originalQueryModel = $query->getModel();
            $query->setModel($relatedModel);
            if ($this->hasFilterClass($filter, EntityCondition::class)) {
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

        return $aliasRight;
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
