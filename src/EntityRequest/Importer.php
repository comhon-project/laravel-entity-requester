<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\MorphCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Enums\AggregationFunction;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\ConditionType;
use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Enums\MathOperator;
use Comhon\EntityRequester\Enums\OrderDirection;
use Comhon\EntityRequester\Exceptions\EnumValueException;
use Comhon\EntityRequester\Exceptions\InvalidConditionOperatorException;
use Comhon\EntityRequester\Exceptions\MalformedValueException;
use Comhon\EntityRequester\Exceptions\MissingValueException;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;
use Comhon\ModelResolverContract\ModelResolverInterface;

class Importer
{
    public function __construct(
        private ModelResolverInterface $modelResolver,
        private ConditionOperatorManagerInterface $operatorManager,
    ) {}

    public function import(array $data, ?string $modelClass = null): EntityRequest
    {
        $resolvedClass = null;
        if (isset($data['entity'])) {
            if (! is_string($data['entity'])) {
                throw new MalformedValueException('entity', 'string');
            }
            $resolvedClass = $this->modelResolver->getClass($data['entity']);
            if (! $resolvedClass) {
                throw new MalformedValueException('entity', 'entity name');
            }
        } elseif ($modelClass) {
            $resolvedClass = $modelClass;
        } else {
            throw new MissingValueException($this->getStack('entity', []));
        }

        if ($modelClass && $modelClass != $resolvedClass) {
            throw new \Exception('entity and model class missmatch');
        }

        $entityRequest = new EntityRequest($resolvedClass);

        if (isset($data['filter'])) {
            $entityRequest->setFilter($this->importFilter($data['filter'], ['filter']));
        }
        if (isset($data['sort'])) {
            $entityRequest->setSort($this->importSort($data['sort'], ['sort']));
        }

        return $entityRequest;
    }

    private function importFilter($filter, array $stack): AbstractCondition
    {
        if (! is_array($filter)) {
            throw new MalformedValueException(implode('.', $stack), 'array');
        }
        if (! isset($filter['type'])) {
            throw new MissingValueException($this->getStack('type', $stack));
        }

        return match ($filter['type']) {
            ConditionType::Condition->value => $this->importCondition($filter, $stack),
            ConditionType::Group->value => $this->importGroup($filter, $stack),
            ConditionType::EntityCondition->value => $this->importEntityCondition($filter, $stack),
            ConditionType::Scope->value => $this->importScope($filter, $stack),
            default => throw new EnumValueException($this->getStack('type', $stack), ConditionType::class)
        };
    }

    private function importCondition(array $filter, array $stack): Condition
    {
        if (! isset($filter['property'])) {
            throw new MissingValueException($this->getStack('property', $stack));
        }
        if (! is_string($filter['property'])) {
            throw new MalformedValueException($this->getStack('property', $stack), 'string');
        }
        $operator = ConditionOperator::Equal;
        if (isset($filter['operator'])) {
            if (! is_string($filter['operator'])) {
                throw new InvalidConditionOperatorException($this->getStack('operator', $stack));
            }
            $operator = ConditionOperator::tryFrom(strtolower($filter['operator']));
            if (! $operator || ! $this->operatorManager->isSupported($operator)) {
                throw new InvalidConditionOperatorException($this->getStack('operator', $stack));
            }
        }
        if (! array_key_exists('value', $filter)) {
            throw new MissingValueException($this->getStack('value', $stack));
        }
        $needArrayValue = $operator == ConditionOperator::In || $operator == ConditionOperator::NotIn;
        $acceptBothTypes = $operator == ConditionOperator::Contains || $operator == ConditionOperator::NotContains;
        $value = $filter['value'];
        $isScalar = is_scalar($value) || is_null($value);

        if ($needArrayValue || ($acceptBothTypes && ! $isScalar)) {
            if (! is_array($value)) {
                throw new MalformedValueException($this->getStack('value', $stack), 'array');
            }
            foreach ($value as $item) {
                if (! is_scalar($item) && ! is_null($item)) {
                    throw new MalformedValueException($this->getStack('value', $stack), 'array of scalars');
                }
            }
        } elseif (! $isScalar) {
            throw new MalformedValueException($this->getStack('value', $stack), 'scalar');
        }

        return new Condition($filter['property'], $operator, $filter['value']);
    }

    private function importGroup(array $filter, array $stack): Group
    {
        if (! isset($filter['operator'])) {
            throw new MissingValueException($this->getStack('operator', $stack));
        }
        if (! is_string($filter['operator'])) {
            throw new EnumValueException($this->getStack('operator', $stack), GroupOperator::class);
        }
        $operator = GroupOperator::tryFrom(strtolower($filter['operator']));
        if (! $operator) {
            throw new EnumValueException($this->getStack('operator', $stack), GroupOperator::class);
        }
        $group = new Group($operator);

        if (isset($filter['filters'])) {
            if (! is_array($filter['filters'])) {
                throw new MalformedValueException($this->getStack('filters', $stack), 'array');
            }
            $stack[] = 'filters';
            foreach ($filter['filters'] as $key => $filter) {
                $stack[] = $key;
                $group->add($this->importFilter($filter, $stack));
                array_pop($stack);
            }
        }

        return $group;
    }

    private function importEntityCondition(array $filter, array $stack): EntityCondition
    {
        if (! isset($filter['property'])) {
            throw new MissingValueException($this->getStack('property', $stack));
        }
        if (! is_string($filter['property'])) {
            throw new MalformedValueException($this->getStack('property', $stack), 'string');
        }
        if (! isset($filter['operator'])) {
            throw new MissingValueException($this->getStack('operator', $stack));
        }
        if (! is_string($filter['operator'])) {
            throw new EnumValueException($this->getStack('operator', $stack), EntityConditionOperator::class);
        }
        $operator = EntityConditionOperator::tryFrom(strtolower($filter['operator']));
        if (! $operator) {
            throw new EnumValueException($this->getStack('operator', $stack), EntityConditionOperator::class);
        }
        $countOperator = null;
        if (isset($filter['count_operator'])) {
            if (! is_string($filter['count_operator'])) {
                throw new EnumValueException($this->getStack('count_operator', $stack), MathOperator::class);
            }
            $countOperator = MathOperator::tryFrom($filter['count_operator']);
            if (! $countOperator) {
                throw new EnumValueException($this->getStack('count_operator', $stack), MathOperator::class);
            }
        }
        $count = null;
        if (isset($filter['count'])) {
            $count = $filter['count'];
            if (! is_int($count) || $count < 1) {
                throw new MalformedValueException($this->getStack('count', $stack), 'integer greater than 0');
            }
        }
        $stack[] = 'filter';

        $importedFilter = isset($filter['filter'])
            ? $this->importFilter($filter['filter'], $stack)
            : null;

        return ! empty($filter['entities'] ?? null)
            ? new MorphCondition(
                $filter['property'],
                $operator,
                $this->importEntities($filter['entities'], $stack),
                $importedFilter,
                $countOperator,
                $count,
            )
            : new EntityCondition(
                $filter['property'],
                $operator,
                $importedFilter,
                $countOperator,
                $count,
            );
    }

    private function importEntities(mixed $entities, array $stack): array
    {
        if (! is_array($entities) || empty($entities)) {
            throw new MalformedValueException($this->getStack('entities', $stack), 'non-empty array of strings');
        }
        foreach ($entities as $entityName) {
            if (! is_string($entityName)) {
                throw new MalformedValueException($this->getStack('entities', $stack), 'non-empty array of strings');
            }
        }

        return $entities;
    }

    private function importScope(array $filter, array $stack): Scope
    {
        if (! isset($filter['name'])) {
            throw new MissingValueException($this->getStack('name', $stack));
        }
        if (! is_string($filter['name'])) {
            throw new MalformedValueException($this->getStack('name', $stack), 'string');
        }
        if (isset($filter['parameters'])) {
            if (! is_array($filter['parameters'])) {
                throw new MalformedValueException($this->getStack('parameters', $stack), 'array');
            }
            if (! array_is_list($filter['parameters'])) {
                throw new MalformedValueException($this->getStack('parameters', $stack), 'array list');
            }
        }

        return new Scope($filter['name'], $filter['parameters'] ?? null);
    }

    private function importSort($sort, array $stack): array
    {
        if (! is_array($sort)) {
            throw new MalformedValueException('sort', 'array');
        }
        $imported = [];
        foreach ($sort as $key => $sortElement) {
            if (! is_array($sortElement)) {
                throw new MalformedValueException($this->getStack($key, $stack), 'array');
            }
            $stack[] = $key;
            $imported[] = $this->importSortElement($sortElement, $stack);
            array_pop($stack);
        }

        return $imported;
    }

    private function importSortElement(array $sort, array $stack)
    {
        if (! isset($sort['property'])) {
            throw new MissingValueException($this->getStack('property', $stack));
        }
        if (! is_string($sort['property'])) {
            throw new MalformedValueException($this->getStack('property', $stack), 'string');
        }
        $property = $sort['property'];
        $order = OrderDirection::Asc;
        if (isset($sort['order'])) {
            if (! is_string($sort['order'])) {
                throw new EnumValueException($this->getStack('order', $stack), OrderDirection::class);
            }
            $order = OrderDirection::tryFrom(strtolower($sort['order']));
            if (! $order) {
                throw new EnumValueException($this->getStack('order', $stack), OrderDirection::class);
            }
        }
        $builtSort = [
            'property' => $property,
            'order' => $order,
        ];
        if (isset($sort['filter'])) {
            if (! is_array($sort['filter'])) {
                throw new MalformedValueException($this->getStack('filter', $stack), 'array');
            }
            $stack[] = 'filter';
            $builtSort['filter'] = $this->importFilter($sort['filter'], $stack);
        }
        if (isset($sort['aggregation'])) {
            if (! is_string($sort['aggregation'])) {
                throw new EnumValueException($this->getStack('aggregation', $stack), AggregationFunction::class);
            }
            $aggregation = AggregationFunction::tryFrom(strtolower($sort['aggregation']));
            if (! $aggregation) {
                throw new EnumValueException($this->getStack('aggregation', $stack), AggregationFunction::class);
            }
            $builtSort['aggregation'] = $aggregation;
        }

        return $builtSort;
    }

    private function getStack(string $property, array $stack): string
    {
        $stack[] = $property;

        return implode('.', $stack);
    }
}
