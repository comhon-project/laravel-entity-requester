<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\RelationshipCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Interfaces\RequestGateInterface;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class Gate implements RequestGateInterface
{
    public function __construct(private RequestSchemaFactoryInterface $requestSchemaFactory) {}

    /**
     * verify if entity request is authorized
     */
    public function authorize(EntityRequest $entityRequest)
    {
        $this->authorizeSort($entityRequest);

        if ($entityRequest->getFilter()) {
            $class = $entityRequest->getModelClass();
            $model = new $class;
            $this->authorizeFilter($model, $entityRequest->getFilter());
        }
    }

    private function authorizeSort(EntityRequest $entityRequest)
    {
        if (empty($entityRequest->getSort())) {
            return;
        }
        $class = $entityRequest->getModelClass();
        $model = new $class;
        foreach ($entityRequest->getSort() as $sortElement) {
            if (strpos($sortElement['property'], '.')) {
                $this->authorizeRelationshipSort($model, $sortElement);
            } else {
                $requestSchema = $this->requestSchemaFactory->get($class);
                if (! $requestSchema->isSortable($sortElement['property'])) {
                    throw new NotSortableException($sortElement['property']);
                }
            }
        }
    }

    private function authorizeFilter(Model $model, AbstractCondition $filter)
    {
        match (get_class($filter)) {
            Condition::class => $this->authorizeCondition($model, $filter),
            Group::class => $this->authorizeGroup($model, $filter),
            Scope::class => $this->authorizeScope($model, $filter),
            RelationshipCondition::class => $this->authorizeRelationshipCondition($model, $filter),
        };
    }

    private function authorizeCondition(Model $model, Condition $condition)
    {
        $propertyId = $condition->getProperty();
        $requestSchema = $this->requestSchemaFactory->get(get_class($model));

        if (! $requestSchema->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }
    }

    private function authorizeGroup(Model $model, Group $group)
    {
        foreach ($group->getConditions() as $condition) {
            $this->authorizeFilter($model, $condition);
        }
    }

    private function authorizeRelationshipCondition(Model $model, RelationshipCondition $condition)
    {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();

        $requestSchema = $this->requestSchemaFactory->get(get_class($model));
        if (! $requestSchema->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }

        if ($filter) {
            $relation = $model->$propertyId();

            $models = $relation instanceof MorphTo
                ? $relation->getRelated()->newModelQuery()->distinct()->pluck($relation->getMorphType())
                    ->filter()
                    ->map(fn ($type) => new (Relation::getMorphedModel($type) ?? $type)())
                    ->all()
                : [$relation->getRelated()];

            foreach ($models as $model) {
                $this->authorizeFilter($model, $filter);
            }
        }
    }

    private function authorizeScope(Model $model, Scope $scope)
    {
        $scopeName = $scope->getName();
        $requestSchema = $this->requestSchemaFactory->get(get_class($model));
        if (! $requestSchema->isScopable($scopeName)) {
            throw new NotScopableException($scopeName);
        }
    }

    private function authorizeRelationshipSort(Model $model, array $relationshipSort)
    {
        $explodedProperty = explode('.', $relationshipSort['property']);
        $parentModel = $model;
        $filter = $relationshipSort['filter'] ?? null;

        for ($i = 0; $i < count($explodedProperty) - 1; $i++) {
            $relationName = $explodedProperty[$i];

            $parentRequestSchema = $this->requestSchemaFactory->get(get_class($parentModel));
            if (! $parentRequestSchema->isSortable($relationName)) {
                throw new NotSortableException($relationName);
            }
            $relation = $parentModel->$relationName();
            $parentModel = $relation->getRelated();
        }

        if ($filter) {
            $this->authorizeFilter($parentModel, $filter);
        }
        $parentRequestSchema = $this->requestSchemaFactory->get(get_class($parentModel));
        $sortProperty = $explodedProperty[array_key_last($explodedProperty)];
        if (! $parentRequestSchema->isSortable($sortProperty)) {
            throw new NotSortableException($sortProperty);
        }
    }
}
