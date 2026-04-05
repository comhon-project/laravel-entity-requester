<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\MorphCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Enums\AggregationFunction;
use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Exceptions\InvalidEntityConditionException;
use Comhon\EntityRequester\Exceptions\InvalidOperatorForPropertyTypeException;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\InvalidToManySortException;
use Comhon\EntityRequester\Exceptions\MorphEntitiesRequiredException;
use Comhon\EntityRequester\Exceptions\NonTraversablePropertyException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\PropertyNotFoundException;
use Comhon\EntityRequester\Exceptions\SchemaNotFoundException;
use Comhon\EntityRequester\Exceptions\UnknownMorphEntityException;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;

class ConsistencyChecker
{
    private const TO_ONE_RELATIONSHIPS = [
        'belongs_to' => true,
        'has_one' => true,
        'has_one_through' => true,
        'morph_one' => true,
    ];

    public function __construct(
        private EntitySchemaFactoryInterface $entitySchemaFactory,
        private EnumSchemaFactoryInterface $enumSchemaFactory,
        private ConditionOperatorManagerInterface $operatorManager,
    ) {}

    public function validate(EntityRequest $entityRequest): void
    {
        $schema = $this->entitySchemaFactory->get($entityRequest->getModelClass());

        if ($entityRequest->getFilter()) {
            $this->validateFilter($entityRequest->getFilter(), $schema, false);
        }

        if ($entityRequest->getSort()) {
            $this->validateSort($entityRequest->getSort(), $schema);
        }
    }

    /**
     * @param  Condition|Group|EntityCondition|MorphCondition|Scope  $filter
     */
    private function validateFilter(AbstractCondition $filter, EntitySchema $schema, bool $insideObject): void
    {
        match (get_class($filter)) {
            Condition::class => $this->validateCondition($filter, $schema),
            Group::class => $this->validateGroup($filter, $schema, $insideObject),
            EntityCondition::class => $this->validateEntityCondition($filter, $schema, $insideObject),
            MorphCondition::class => $this->validateMorphCondition($filter, $schema, $insideObject),
            Scope::class => $this->validateScope($filter, $schema, $insideObject),
        };
    }

    private function validateCondition(Condition $condition, EntitySchema $schema): void
    {
        $property = $schema->getProperty($condition->getProperty());
        if (! $property) {
            throw new PropertyNotFoundException($condition->getProperty(), $schema->getId());
        }
        $propertyType = $property['type'];
        $operator = $condition->getOperator();

        $allowedOperators = $this->operatorManager->getOperatorsForPropertyType($propertyType);
        if (! in_array($operator, $allowedOperators)) {
            throw new InvalidOperatorForPropertyTypeException($operator, $propertyType, $allowedOperators);
        }
    }

    private function validateGroup(Group $group, EntitySchema $schema, bool $insideObject): void
    {
        foreach ($group->getConditions() as $condition) {
            $this->validateFilter($condition, $schema, $insideObject);
        }
    }

    private function validateEntityCondition(EntityCondition $condition, EntitySchema $schema, bool $insideObject): void
    {
        $propertyId = $condition->getProperty();
        $property = $schema->getProperty($propertyId);

        if (! $property) {
            throw new PropertyNotFoundException($propertyId, $schema->getId());
        }

        if (($property['relationship_type'] ?? null) === 'morph_to') {
            if ($condition->getFilter()) {
                throw new MorphEntitiesRequiredException($propertyId);
            }

            return;
        }

        if (! isset($property['entity'])) {
            throw new InvalidEntityConditionException(
                "Property '$propertyId' does not support entity condition filtering"
            );
        }

        if ($property['type'] === 'object') {
            if ($condition->getOperator() === EntityConditionOperator::HasNot && $condition->getFilter()) {
                throw new InvalidEntityConditionException(
                    "Operator 'has_not' with filter is not supported on object property '$propertyId'"
                );
            }
            if ($condition->getCountOperator() !== null || $condition->getCount() !== null) {
                throw new InvalidEntityConditionException(
                    "Options 'count_operator' and 'count' are not supported on object property '$propertyId'"
                );
            }
        }

        $filter = $condition->getFilter();
        if ($filter) {
            $childSchema = $this->entitySchemaFactory->get($property['entity']);
            $this->validateFilter($filter, $childSchema, $property['type'] === 'object');
        }
    }

    private function validateMorphCondition(MorphCondition $condition, EntitySchema $schema, bool $insideObject): void
    {
        if ($insideObject) {
            throw new InvalidEntityConditionException(
                'MorphCondition is not supported inside object entity conditions'
            );
        }

        $propertyId = $condition->getProperty();
        $property = $schema->getProperty($propertyId);

        if (! $property) {
            throw new PropertyNotFoundException($propertyId, $schema->getId());
        }

        if (($property['relationship_type'] ?? null) !== 'morph_to') {
            throw new InvalidEntityConditionException(
                "Property '$propertyId' is not a morph_to relationship"
            );
        }

        $filter = $condition->getFilter();
        foreach ($condition->getEntities() as $entityName) {
            try {
                $entitySchema = $this->entitySchemaFactory->get($entityName);
            } catch (SchemaNotFoundException) {
                throw new UnknownMorphEntityException($entityName);
            }
            if ($filter) {
                $this->validateFilter($filter, $entitySchema, false);
            }
        }
    }

    private function validateScope(Scope $scope, EntitySchema $schema, bool $insideObject): void
    {
        if ($insideObject) {
            throw new InvalidEntityConditionException(
                'Scopes are not supported inside object entity conditions'
            );
        }

        $scopeName = $scope->getName();
        $scopeSchema = $schema->getScope($scopeName);
        if (! $scopeSchema) {
            throw new NotScopableException($scopeName);
        }

        $this->validateScopeParameters($scopeName, $scope->getParameters() ?? [], $scopeSchema['parameters'] ?? []);
    }

    private function validateScopeParameters(string $scopeName, array $values, array $parameterSchemas): void
    {
        if (count($values) > count($parameterSchemas)) {
            throw new InvalidScopeParametersException($scopeName);
        }

        foreach ($parameterSchemas as $index => $paramSchema) {
            if (! array_key_exists($index, $values)) {
                throw new InvalidScopeParametersException($scopeName);
            }

            $value = $values[$index];

            if ($value === null) {
                if (! ($paramSchema['nullable'] ?? false)) {
                    throw new InvalidScopeParametersException($scopeName);
                }

                continue;
            }

            $type = $paramSchema['type'] ?? null;
            if (! $this->isValidType($value, $type)) {
                throw new InvalidScopeParametersException($scopeName);
            }

            if (isset($paramSchema['enum'])) {
                $enumSchema = $this->enumSchemaFactory->get($paramSchema['enum']);
                if (! $enumSchema->hasCase($value)) {
                    throw new InvalidScopeParametersException($scopeName);
                }
            }
        }
    }

    private function isValidType(mixed $value, ?string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'float' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'datetime', 'date', 'time' => is_string($value),
            default => true,
        };
    }

    private function validateSort(array $sort, EntitySchema $schema): void
    {
        // Multiple to-many sorts with count/sum/avg produce a cartesian product
        // between joined tables, which corrupts aggregation results.
        // min/max are not affected since duplicates don't change their result.
        $hasUnsafeAggregation = false;
        foreach ($sort as $sortElement) {
            $isUnsafeAggregation = $this->validateSortElement($sortElement, $schema);
            if ($isUnsafeAggregation) {
                if ($hasUnsafeAggregation) {
                    throw new InvalidToManySortException($sortElement['property']);
                }
                $hasUnsafeAggregation = true;
            }
        }
    }

    private function validateSortElement(array $sortElement, EntitySchema $schema): bool
    {
        $segments = explode('.', $sortElement['property']);
        $currentSchema = $schema;
        $isToOne = true;

        foreach ($segments as $i => $segment) {
            $property = $currentSchema->getProperty($segment);
            if (! $property) {
                throw new PropertyNotFoundException($segment, $currentSchema->getId());
            }

            if ($i < count($segments) - 1) {
                if (! isset($property['entity'])) {
                    throw new NonTraversablePropertyException($segment);
                }
                $relationshipType = $property['relationship_type'] ?? null;
                if ($isToOne && $relationshipType && ! isset(self::TO_ONE_RELATIONSHIPS[$relationshipType])) {
                    $isToOne = false;
                }
                $currentSchema = $this->entitySchemaFactory->get($property['entity']);
            }
        }

        if (! $isToOne && ! isset($sortElement['aggregation'])) {
            throw new InvalidToManySortException($sortElement['property']);
        }

        if (isset($sortElement['filter'])) {
            $this->validateFilter($sortElement['filter'], $currentSchema, false);
        }

        return ! $isToOne && in_array($sortElement['aggregation'], [AggregationFunction::Count, AggregationFunction::Sum, AggregationFunction::Avg]);
    }
}
