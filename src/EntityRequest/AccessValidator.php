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
use Comhon\EntityRequester\Interfaces\AccessValidatorInterface;
use Comhon\EntityRequester\Interfaces\RequestAccessFactoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class AccessValidator implements AccessValidatorInterface
{
    public function __construct(private RequestAccessFactoryInterface $requestAccessFactory) {}

    /**
     * validate access of each referenced properties in entity request
     */
    public function validate(EntityRequest $entityRequest)
    {
        $this->validateSort($entityRequest);

        if ($entityRequest->getFilter()) {
            $class = $entityRequest->getModelClass();
            $model = new $class;
            $this->validateFilter($model, $entityRequest->getFilter());
        }
    }

    private function validateSort(EntityRequest $entityRequest)
    {
        if (empty($entityRequest->getSort())) {
            return;
        }
        $class = $entityRequest->getModelClass();
        $model = new $class;
        foreach ($entityRequest->getSort() as $sortElement) {
            if (strpos($sortElement['property'], '.')) {
                $this->validateRelationshipSort($model, $sortElement);
            } else {
                $access = $this->requestAccessFactory->get($class);
                if (! $access->isSortable($sortElement['property'])) {
                    throw new NotSortableException($sortElement['property']);
                }
            }
        }
    }

    private function validateFilter(Model $model, AbstractCondition $filter)
    {
        match (get_class($filter)) {
            Condition::class => $this->validateCondition($model, $filter),
            Group::class => $this->validateGroup($model, $filter),
            Scope::class => $this->validateScope($model, $filter),
            RelationshipCondition::class => $this->validateRelationshipCondition($model, $filter),
        };
    }

    private function validateCondition(Model $model, Condition $condition)
    {
        $propertyId = $condition->getProperty();
        $access = $this->requestAccessFactory->get(get_class($model));

        if (! $access->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }
    }

    private function validateGroup(Model $model, Group $group)
    {
        foreach ($group->getConditions() as $condition) {
            $this->validateFilter($model, $condition);
        }
    }

    private function validateRelationshipCondition(Model $model, RelationshipCondition $condition)
    {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();

        $access = $this->requestAccessFactory->get(get_class($model));
        if (! $access->isFiltrable($propertyId)) {
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
                $this->validateFilter($model, $filter);
            }
        }
    }

    private function validateScope(Model $model, Scope $scope)
    {
        $scopeName = $scope->getName();
        $access = $this->requestAccessFactory->get(get_class($model));
        if (! $access->isScopable($scopeName)) {
            throw new NotScopableException($scopeName);
        }
    }

    private function validateRelationshipSort(Model $model, array $relationshipSort)
    {
        $explodedProperty = explode('.', $relationshipSort['property']);
        $parentModel = $model;
        $filter = $relationshipSort['filter'] ?? null;

        for ($i = 0; $i < count($explodedProperty) - 1; $i++) {
            $relationName = $explodedProperty[$i];

            $parentAccess = $this->requestAccessFactory->get(get_class($parentModel));
            if (! $parentAccess->isSortable($relationName)) {
                throw new NotSortableException($relationName);
            }
            $relation = $parentModel->$relationName();
            $parentModel = $relation->getRelated();
        }

        if ($filter) {
            $this->validateFilter($parentModel, $filter);
        }
        $parentAccess = $this->requestAccessFactory->get(get_class($parentModel));
        $sortProperty = $explodedProperty[array_key_last($explodedProperty)];
        if (! $parentAccess->isSortable($sortProperty)) {
            throw new NotSortableException($sortProperty);
        }
    }
}
