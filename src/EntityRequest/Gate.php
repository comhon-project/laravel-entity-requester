<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\RelationshipCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Exceptions\SchemaNotFoundException;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\RequestGateInterface;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class Gate implements RequestGateInterface
{
    public function __construct(
        private RequestSchemaFactoryInterface $requestSchemaFactory,
        private EntitySchemaFactoryInterface $entitySchemaFactory,
    ) {}

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
        $requestSchema = $this->requestSchemaFactory->get($class);
        foreach ($entityRequest->getSort() as $sortElement) {
            $segments = explode('.', $sortElement['property']);
            $rootPropertyId = $segments[0];

            if (count($segments) === 1) {
                if (! $requestSchema->isSortable($rootPropertyId)) {
                    throw new NotSortableException($rootPropertyId);
                }
            } else {
                $entitySchema = $this->entitySchemaFactory->get($class);
                $rootProperty = $entitySchema->getProperty($rootPropertyId);
                if (! $rootProperty) {
                    throw new NotSortableException($rootPropertyId);
                }

                if ($rootProperty['type'] === 'object') {
                    $this->authorizeObjectProperties($entitySchema, $segments, 'sortable');
                } else {
                    $this->authorizeRelationshipSort($model, $segments, $sortElement['filter'] ?? null);
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
        $segments = explode('.', $condition->getProperty());

        if (count($segments) === 1) {
            $requestSchema = $this->requestSchemaFactory->get(get_class($model));
            if (! $requestSchema->isFiltrable($segments[0])) {
                throw new NotFiltrableException($segments[0]);
            }
        } else {
            $entitySchema = $this->entitySchemaFactory->get(get_class($model));
            $this->authorizeObjectProperties($entitySchema, $segments, 'filtrable');
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

    private function authorizeObjectProperties(EntitySchema $entitySchema, array $segments, string $type)
    {
        $currentEntitySchema = $entitySchema;
        $requestSchema = $this->requestSchemaFactory->get($entitySchema->getId());
        $throwException = fn (string $segment) => $type === 'filtrable'
            ? throw new NotFiltrableException($segment)
            : throw new NotSortableException($segment);

        for ($i = 0; $i < count($segments); $i++) {
            $segment = $segments[$i];

            $isAuthorized = $type === 'filtrable'
                ? $requestSchema->isFiltrable($segment)
                : $requestSchema->isSortable($segment);

            if (! $isAuthorized) {
                $throwException($segment);
            }

            if ($i < count($segments) - 1) {
                $nextSegment = $segments[$i + 1];
                $property = $currentEntitySchema->getProperty($segment);
                if ($property['type'] !== 'object' || ! isset($property['entity'])) {
                    $throwException($nextSegment);
                }

                try {
                    $entityId = $property['entity'];
                    $requestSchema = $this->requestSchemaFactory->get($entityId);
                    $currentEntitySchema = $this->entitySchemaFactory->get($entityId);
                } catch (SchemaNotFoundException) {
                    $throwException($nextSegment);
                }
            }
        }
    }

    private function authorizeRelationshipSort(Model $model, array $segments, ?AbstractCondition $filter)
    {
        $parentModel = $model;

        for ($i = 0; $i < count($segments) - 1; $i++) {
            $segmentName = $segments[$i];

            $parentRequestSchema = $this->requestSchemaFactory->get(get_class($parentModel));
            if (! $parentRequestSchema->isSortable($segmentName)) {
                throw new NotSortableException($segmentName);
            }

            $entitySchema = $this->entitySchemaFactory->get(get_class($parentModel));
            $property = $entitySchema->getProperty($segmentName);
            if ($property && $property['type'] === 'object') {
                $this->authorizeObjectProperties(
                    $entitySchema,
                    array_slice($segments, $i),
                    'sortable',
                );

                return;
            }

            $relation = $parentModel->$segmentName();
            $parentModel = $relation->getRelated();
        }

        if ($filter) {
            $this->authorizeFilter($parentModel, $filter);
        }
        $parentRequestSchema = $this->requestSchemaFactory->get(get_class($parentModel));
        $sortProperty = $segments[array_key_last($segments)];
        if (! $parentRequestSchema->isSortable($sortProperty)) {
            throw new NotSortableException($sortProperty);
        }
    }
}
