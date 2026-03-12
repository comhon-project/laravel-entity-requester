<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\DTOs\AbstractCondition;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\EntitySchema;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\RequestGateInterface;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class Gate implements RequestGateInterface
{
    public function __construct(
        private RequestSchemaFactoryInterface $requestSchemaFactory,
        private EntitySchemaFactoryInterface $entitySchemaFactory,
        private ModelResolverInterface $modelResolver,
    ) {}

    /**
     * verify if entity request is authorized
     */
    public function authorize(EntityRequest $entityRequest)
    {
        $this->authorizeSort($entityRequest);

        if ($entityRequest->getFilter()) {
            $entitySchema = $this->entitySchemaFactory->get($entityRequest->getModelClass());
            $this->authorizeFilter($entitySchema, $entityRequest->getFilter());
        }
    }

    private function authorizeSort(EntityRequest $entityRequest)
    {
        if (empty($entityRequest->getSort())) {
            return;
        }
        $class = $entityRequest->getModelClass();
        $entitySchema = $this->entitySchemaFactory->get($class);
        foreach ($entityRequest->getSort() as $sortElement) {
            $segments = explode('.', $sortElement['property']);
            $this->authorizeSortPath($entitySchema, $segments, $sortElement['filter'] ?? null);
        }
    }

    private function authorizeFilter(EntitySchema $entitySchema, AbstractCondition $filter)
    {
        match (get_class($filter)) {
            Condition::class => $this->authorizeCondition($entitySchema, $filter),
            Group::class => $this->authorizeGroup($entitySchema, $filter),
            EntityCondition::class => $this->authorizeEntityCondition($entitySchema, $filter),
            Scope::class => $this->authorizeScope($entitySchema, $filter),
        };
    }

    private function authorizeCondition(EntitySchema $entitySchema, Condition $condition)
    {
        $requestSchema = $this->requestSchemaFactory->get($entitySchema->getId());
        if (! $requestSchema->isFiltrable($condition->getProperty())) {
            throw new NotFiltrableException($condition->getProperty());
        }
    }

    private function authorizeGroup(EntitySchema $entitySchema, Group $group)
    {
        foreach ($group->getConditions() as $condition) {
            $this->authorizeFilter($entitySchema, $condition);
        }
    }

    private function authorizeEntityCondition(EntitySchema $entitySchema, EntityCondition $condition)
    {
        $propertyId = $condition->getProperty();
        $filter = $condition->getFilter();

        $property = $entitySchema->getProperty($propertyId);
        if (! $property) {
            throw new NotFiltrableException($propertyId);
        }

        $requestSchema = $this->requestSchemaFactory->get($entitySchema->getId());
        if (! $requestSchema->isFiltrable($propertyId)) {
            throw new NotFiltrableException($propertyId);
        }

        if (! $filter) {
            return;
        }

        if ($property['type'] === 'object') {
            $entityId = $property['entity'];
            $childEntitySchema = $this->entitySchemaFactory->get($entityId);
            $this->authorizeFilter($childEntitySchema, $filter);
        } elseif ($property['type'] === 'relationship') {
            $class = $this->modelResolver->getClass($entitySchema->getId());
            $model = new $class;
            $relation = $model->$propertyId();

            $models = $relation instanceof MorphTo
                ? $relation->getRelated()->newModelQuery()->distinct()->pluck($relation->getMorphType())
                    ->filter()
                    ->map(fn ($type) => new (Relation::getMorphedModel($type) ?? $type)())
                    ->all()
                : [$relation->getRelated()];

            foreach ($models as $relatedModel) {
                $relatedEntitySchema = $this->entitySchemaFactory->get(get_class($relatedModel));
                $this->authorizeFilter($relatedEntitySchema, $filter);
            }
        } else {
            throw new NotFiltrableException($propertyId);
        }
    }

    private function authorizeScope(EntitySchema $entitySchema, Scope $scope)
    {
        $requestSchema = $this->requestSchemaFactory->get($entitySchema->getId());
        if (! $requestSchema->isScopable($scope->getName())) {
            throw new NotScopableException($scope->getName());
        }
    }

    private function authorizeSortPath(EntitySchema $entitySchema, array $segments, ?AbstractCondition $filter = null)
    {
        $currentEntitySchema = $entitySchema;

        for ($i = 0; $i < count($segments); $i++) {
            $segment = $segments[$i];

            $property = $currentEntitySchema->getProperty($segment);
            if (! $property) {
                throw new NotSortableException($segment);
            }

            $requestSchema = $this->requestSchemaFactory->get($currentEntitySchema->getId());
            if (! $requestSchema->isSortable($segment)) {
                throw new NotSortableException($segment);
            }

            if ($i < count($segments) - 1) {

                if ($property['type'] !== 'object' && $property['type'] !== 'relationship') {
                    throw new NotSortableException($segments[$i + 1]);
                }
                $currentEntitySchema = $this->entitySchemaFactory->get($property['entity']);
            }
        }

        if ($filter) {
            $this->authorizeFilter($currentEntitySchema, $filter);
        }
    }
}
