<?php

namespace Comhon\EntityRequester\DTOs;

use Comhon\EntityRequester\Enums\AggregationFunction;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Enums\OrderDirection;
use Illuminate\Database\Eloquent\Model;

class EntityRequest
{
    /**
     * @var Condition|Group|EntityCondition|Scope
     */
    private ?AbstractCondition $filter = null;

    private array $sort = [];

    public function __construct(private string $modelClass)
    {
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

    public function setSort(array $sort): void
    {
        $this->sort = $sort;
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
}
