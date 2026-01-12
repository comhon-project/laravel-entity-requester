<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\AggregationFunction;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\ConditionType;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Enums\MathOperator;
use Comhon\EntityRequester\Enums\OrderDirection;
use Comhon\EntityRequester\Enums\RelationshipConditionOperator;
use Comhon\EntityRequester\Exceptions\EnumValueException;
use Comhon\EntityRequester\Exceptions\InvalidConditionOperatorException;
use Comhon\EntityRequester\Exceptions\MalformedValueException;
use Comhon\EntityRequester\Exceptions\MissingValueException;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Database\Eloquent\Model;

class EntityRequest
{
    private string $modelClass;

    /**
     * @var Condition|Group|RelationshipCondition|Scope
     */
    private ?AbstractCondition $filter = null;

    private array $sort = [];

    public function __construct(?array $data = null, ?string $modelClass = null)
    {
        if ($data) {
            $this->fill($data);
        }
        if (! isset($this->modelClass)) {
            $this->modelClass = $modelClass ?? throw new MissingValueException($this->getStack('entity', []));
        } elseif ($modelClass && $modelClass != $this->modelClass) {
            throw new \Exception('entity and model class missmatch');
        }
        if (! is_subclass_of($this->modelClass, Model::class)) {
            throw new \Exception('model class must be instance of '.Model::class);
        }
    }

    /**
     * get requested model fully qualified class name
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getFilter(): ?AbstractCondition
    {
        return $this->filter;
    }

    public function setFilter(AbstractCondition $filter)
    {
        $this->filter = $filter;
    }

    /**
     * @param  AbstractCondition[]|AbstractCondition  $filter
     */
    public function addFilter(array|AbstractCondition $filters, bool $and = true): static
    {
        if (! is_array($filters)) {
            $filters = [$filters];
        }
        if (count($filters) == 0) {
            return $this;
        }
        $operator = $and ? GroupOperator::And : GroupOperator::Or;
        if (! $this->filter instanceof Group || $this->filter?->getOperator() != $operator) {
            $currentFilter = $this->filter;
            $this->filter = new Group($operator);
            if ($currentFilter) {
                $this->filter->add($currentFilter);
            }
        }
        foreach ($filters as $filter) {
            if (! ($filter instanceof AbstractCondition)) {
                throw new \Exception('each filters element must be instance of AbstractCondition');
            }
            $this->filter->add($filter);
        }

        return $this;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function addSort(
        string $property,
        OrderDirection $order = OrderDirection::Asc,
        ?AbstractCondition $filter = null,
        ?AggregationFunction $aggregation = null,
    ) {
        $this->sort[] = [
            'property' => $property,
            'order' => $order,
            'filter' => $filter,
            'aggregation' => $aggregation,
        ];
    }

    /**
     * fill request with given data
     */
    private function fill(array $data)
    {
        if (isset($data['entity'])) {
            if (! is_string($data['entity'])) {
                throw new MalformedValueException('entity', 'string');
            }
            $class = app(ModelResolverInterface::class)->getClass($data['entity']);
            if (! $class) {
                throw new MalformedValueException('entity', 'entity name');
            }
            $this->modelClass = $class;
        }
        if (isset($data['sort'])) {
            $this->sort = $this->importSort($data['sort'], ['sort']);
        }
        if (isset($data['filter'])) {
            $this->filter = $this->importFilter($data['filter'], ['filter']);
        }
    }

    /**
     * @param  array  $filter
     */
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
            ConditionType::RelationshipCondition->value => $this->importRelationshipCondition($filter, $stack),
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
            if (! $operator || ! $operator->isSupported()) {
                throw new InvalidConditionOperatorException($this->getStack('operator', $stack));
            }
        }
        if (! array_key_exists('value', $filter)) {
            throw new MissingValueException($this->getStack('value', $stack));
        }
        $needArrayValue = $operator == ConditionOperator::In || $operator == ConditionOperator::NotIn;
        if ($needArrayValue && ! is_array($filter['value'])) {
            throw new MalformedValueException($this->getStack('value', $stack), 'array');
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

    private function importRelationshipCondition(array $filter, array $stack): RelationshipCondition
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
            throw new EnumValueException($this->getStack('operator', $stack), RelationshipConditionOperator::class);
        }
        $operator = RelationshipConditionOperator::tryFrom(strtolower($filter['operator']));
        if (! $operator) {
            throw new EnumValueException($this->getStack('operator', $stack), RelationshipConditionOperator::class);
        }
        $countOperator = MathOperator::GreaterThanOrEqual;
        if (isset($filter['count_operator'])) {
            if (! is_string($filter['count_operator'])) {
                throw new EnumValueException($this->getStack('count_operator', $stack), MathOperator::class);
            }
            $countOperator = MathOperator::tryFrom($filter['count_operator']);
            if (! $countOperator) {
                throw new EnumValueException($this->getStack('count_operator', $stack), MathOperator::class);
            }
        }
        $count = 1;
        if (isset($filter['count'])) {
            $count = $filter['count'];
            if (! is_int($count) || $count < 1) {
                throw new MalformedValueException($this->getStack('count', $stack), 'integer greater than 0');
            }
        }
        $stack[] = 'filter';

        return new RelationshipCondition(
            $filter['property'],
            $operator,
            isset($filter['filter'])
                ? $this->importFilter($filter['filter'], $stack)
                : null,
            $countOperator,
            $count,
        );
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

    private function importSort($sort, array $stack = []): array
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

    private function importSortElement(array $sort, array $stack = [])
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
