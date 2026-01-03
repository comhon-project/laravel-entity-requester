<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\RelationshipCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Enums\OrderDirection;
use Comhon\EntityRequester\Enums\RelationshipConditionOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntityRequestTest extends TestCase
{
    public function test_instanciate_entity_request_only_model()
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $this->assertEquals(User::class, $entityRequest->getModelClass());
    }

    public function test_instanciate_entity_request_redondant_model_class()
    {
        $entityRequest = new EntityRequest(null, User::class);

        $this->assertEquals(User::class, $entityRequest->getModelClass());
    }

    public function test_instanciate_entity_request_redondant_model()
    {
        $entityRequest = new EntityRequest(['entity' => 'user'], User::class);

        $this->assertEquals(User::class, $entityRequest->getModelClass());
    }

    public function test_instanciate_entity_request_missmatch_model()
    {
        $this->expectExceptionMessage('entity and model class missmatch');
        new EntityRequest(['entity' => 'visible'], User::class);
    }

    public function test_instanciate_entity_request_missing_model()
    {
        $this->expectExceptionMessage("Property 'entity' is required");
        new EntityRequest;
    }

    public function test_instanciate_entity_request_invalid_model()
    {
        $this->expectExceptionMessage("Invalid property 'entity', must be a entity name");
        new EntityRequest(['entity' => 'foo']);
    }

    public function test_instanciate_entity_request_invalid_model_class()
    {
        $this->expectExceptionMessage("model class must be instance of Illuminate\Database\Eloquent\Model");
        new EntityRequest(null, 'foo');
    }

    public function test_instanciate_entity_request_invalid_model_type()
    {
        $this->expectExceptionMessage("Invalid property 'entity', must be a string");
        new EntityRequest(['entity' => 123]);
    }

    public function test_instanciate_entity_request_valid()
    {
        $entityRequest = new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'group',
                'operator' => 'or',
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => '>',
                        'property' => 'foo',
                        'value' => 12,
                    ],
                    [
                        'type' => 'scope',
                        'name' => 'my_scope',
                    ],
                    [
                        'type' => 'scope',
                        'name' => 'my_scope',
                        'parameters' => ['foo'],
                    ],
                    [
                        'type' => 'group',
                        'operator' => 'and',
                        'filters' => [],
                    ],
                    [
                        'type' => 'relationship_condition',
                        'operator' => 'Has',
                        'property' => 'foo',
                    ],
                    [
                        'type' => 'relationship_condition',
                        'operator' => 'Has',
                        'property' => 'foo',
                        'filter' => [
                            'type' => 'condition',
                            'operator' => '=',
                            'property' => 'foo',
                            'value' => 13,
                        ],
                    ],
                ],
            ],
            'sort' => [
                [
                    'property' => 'foo.bar',
                    'order' => 'desc',
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'foo',
                        'value' => 123,
                    ],
                ],
                [
                    'property' => 'foo.bar',
                ],
            ],
        ]);

        /** @var Group $filter */
        $filter = $entityRequest->getFilter();
        $this->assertInstanceOf(Group::class, $filter);
        $this->assertEquals(GroupOperator::Or, $filter->getOperator());
        $this->assertCount(6, $filter->getConditions());

        /** @var Condition $condition */
        $condition = $filter->getConditions()[0];
        $this->assertInstanceOf(Condition::class, $condition);
        $this->assertEquals(ConditionOperator::GreaterThan, $condition->getOperator());
        $this->assertEquals('foo', $condition->getProperty());
        $this->assertEquals(12, $condition->getValue());

        /** @var Scope $scope */
        $scope = $filter->getConditions()[2];
        $this->assertInstanceOf(Scope::class, $scope);
        $this->assertEquals('my_scope', $scope->getName());
        $this->assertEquals(['foo'], $scope->getParameters());

        /** @var RelationshipCondition $relationshipCondition */
        $relationshipCondition = $filter->getConditions()[5];
        $this->assertInstanceOf(RelationshipCondition::class, $relationshipCondition);
        $this->assertEquals(RelationshipConditionOperator::Has, $relationshipCondition->getOperator());
        $this->assertEquals('foo', $relationshipCondition->getProperty());

        /** @var Condition $condition */
        $condition = $relationshipCondition->getFilter();
        $this->assertInstanceOf(Condition::class, $condition);
        $this->assertEquals(13, $condition->getValue());

        $sort = $entityRequest->getSort();
        $this->assertCount(2, $sort);
        $this->assertEquals('foo.bar', $sort[0]['property']);
        $this->assertEquals(OrderDirection::Desc, $sort[0]['order']);
        $this->assertInstanceOf(Condition::class, $sort[0]['filter']);
        $this->assertEquals(123, $sort[0]['filter']->getValue());
    }

    #[DataProvider('provider_build_entity_request_invalid')]
    public function test_instanciate_entity_request_invalid($data, $error)
    {
        $isConditionOperatorError = $error == "Invalid property 'filter.operator', must be one of [=, <>, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE]";
        if ($isConditionOperatorError && config('database.default') == 'pgsql') {
            $error = str_replace(']', ', ILIKE, NOT ILIKE]', $error);
        }

        $this->expectExceptionMessage($error);
        new EntityRequest([
            'entity' => 'user',
            ...$data,
        ]);
    }

    public static function provider_build_entity_request_invalid()
    {
        return [
            [
                ['filter' => 'foo'],
                "Invalid property 'filter', must be a array",
            ],
            [
                ['filter' => []],
                "Property 'filter.type' is required",
            ],
            [
                ['filter' => ['type' => 'foo']],
                "Invalid property 'filter.type', must be one of [condition, group, relationship_condition, scope]",
            ],
            [
                ['filter' => ['type' => 'group']],
                "Property 'filter.operator' is required",
            ],
            [
                ['filter' => ['type' => 'group', 'operator' => 'foo']],
                "Invalid property 'filter.operator', must be one of [OR, AND]",
            ],
            [
                ['filter' => ['type' => 'group', 'operator' => ['foo']]],
                "Invalid property 'filter.operator', must be one of [OR, AND]",
            ],
            [
                ['filter' => ['type' => 'group', 'operator' => 'OR', 'filters' => 'foo']],
                "Invalid property 'filter.filters', must be a array",
            ],
            [
                ['filter' => ['type' => 'group', 'operator' => 'OR', 'filters' => [
                    ['type' => 'group'],
                ]]],
                "Property 'filter.filters.0.operator' is required",
            ],
            [
                ['filter' => ['type' => 'group', 'operator' => 'OR', 'filters' => [
                    ['type' => 'group', 'operator' => 'OR'],
                    ['operator' => 'OR'],
                ]]],
                "Property 'filter.filters.1.type' is required",
            ],
            [
                ['filter' => ['type' => 'condition']],
                "Property 'filter.property' is required",
            ],
            [
                ['filter' => ['type' => 'condition', 'property' => 123]],
                "Invalid property 'filter.property', must be a string",
            ],
            [
                ['filter' => ['type' => 'condition', 'property' => 'foo']],
                "Property 'filter.value' is required",
            ],
            [
                ['filter' => ['type' => 'condition', 'property' => 'foo', 'operator' => 'foo']],
                "Invalid property 'filter.operator', must be one of [=, <>, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE]",
            ],
            [
                ['filter' => ['type' => 'condition', 'property' => 'foo', 'operator' => ['foo']]],
                "Invalid property 'filter.operator', must be one of [=, <>, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE]",
            ],
            [
                ['filter' => ['type' => 'condition', 'property' => 'foo', 'operator' => 'IN', 'value' => 123]],
                "Invalid property 'filter.value', must be a array",
            ],
            [
                ['filter' => ['type' => 'scope']],
                "Property 'filter.name' is required",
            ],
            [
                ['filter' => ['type' => 'scope', 'name' => 123]],
                "Invalid property 'filter.name', must be a string",
            ],
            [
                ['filter' => ['type' => 'scope', 'name' => 'foo', 'parameters' => 'foo']],
                "Invalid property 'filter.parameters', must be a array",
            ],
            [
                ['filter' => ['type' => 'scope', 'name' => 'foo', 'parameters' => ['foo' => 'foo']]],
                "Invalid property 'filter.parameters', must be a array list",
            ],
            [
                ['filter' => ['type' => 'relationship_condition']],
                "Property 'filter.property' is required",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 123]],
                "Invalid property 'filter.property', must be a string",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo']],
                "filter.operator' is required",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => 'foo']],
                "Invalid property 'filter.operator', must be one of [HAS, HAS_NOT]",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => ['foo']]],
                "Invalid property 'filter.operator', must be one of [HAS, HAS_NOT]",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => 'HAS', 'filter' => 'foo']],
                "Invalid property 'filter.filter', must be a array",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => 'HAS', 'count_operator' => 'foo']],
                "filter.count_operator', must be one of [=, <>, <, <=, >, >=]",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => 'HAS', 'count_operator' => ['foo']]],
                "filter.count_operator', must be one of [=, <>, <, <=, >, >=]",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => 'HAS', 'count' => 'foo']],
                "Invalid property 'filter.count', must be a integer greater than 0",
            ],
            [
                ['filter' => ['type' => 'relationship_condition', 'property' => 'foo', 'operator' => 'HAS', 'count' => 0]],
                "Invalid property 'filter.count', must be a integer greater than 0",
            ],
            [
                ['sort' => 'foo'],
                "Invalid property 'sort', must be a array",
            ],
            [
                ['sort' => ['foo']],
                "Invalid property 'sort.0', must be a array",
            ],
            [
                ['sort' => [[]]],
                "Property 'sort.0.property' is required",
            ],
            [
                ['sort' => [['property' => 123]]],
                "Invalid property 'sort.0.property', must be a string",
            ],
            [
                ['sort' => [['property' => 'foo', 'order' => 123]]],
                "Invalid property 'sort.0.order', must be one of [ASC, DESC]",
            ],
            [
                ['sort' => [['property' => 'foo', 'order' => 'foo']]],
                "Invalid property 'sort.0.order', must be one of [ASC, DESC]",
            ],
            [
                ['sort' => [['property' => 'foo'], []]],
                "Property 'sort.1.property' is required",
            ],
            [
                ['sort' => [['property' => 'foo', 'filter' => 'foo']]],
                "Invalid property 'sort.0.filter', must be a array",
            ],
            [
                ['sort' => [['property' => 'foo', 'filter' => []]]],
                "Property 'sort.0.filter.type' is required",
            ],
            [
                ['sort' => [['property' => 'friends.id', 'aggregation' => ['foo']]]],
                "Invalid property 'sort.0.aggregation', must be one of [COUNT, SUM, AVG, MIN, MAX]",
            ],
            [
                ['sort' => [['property' => 'friends.id', 'aggregation' => 'foo']]],
                "Invalid property 'sort.0.aggregation', must be one of [COUNT, SUM, AVG, MIN, MAX]",
            ],
        ];
    }

    #[DataProvider('providerBoolean')]
    public function test_add_filter_abstract_condition(bool $and)
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $condition = new Condition('foo', ConditionOperator::Equal, 'bar');
        $entityRequest->addFilter($condition, $and);
        $this->assertInstanceOf(Group::class, $entityRequest->getFilter());

        /** @var Group $group */
        $group = $entityRequest->getFilter();
        $this->assertEquals($and ? GroupOperator::And : GroupOperator::Or, $group->getOperator());
        $this->assertCount(1, $group->getConditions());
        $this->assertTrue($condition === $group->getConditions()[0]);
    }

    #[DataProvider('providerBoolean')]
    public function test_add_filter_array(bool $and)
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $conditions = [
            new Condition('foo', ConditionOperator::Equal, 'bar'),
            new Condition('foo', ConditionOperator::Equal, 'baz'),
        ];
        $entityRequest->addFilter($conditions, $and);
        $this->assertInstanceOf(Group::class, $entityRequest->getFilter());

        /** @var Group $group */
        $group = $entityRequest->getFilter();
        $this->assertEquals($and ? GroupOperator::And : GroupOperator::Or, $group->getOperator());
        $this->assertCount(2, $group->getConditions());
    }

    public function test_add_filter_same_operator()
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $groupOr = new Group(GroupOperator::Or);
        $groupOr->add(new Condition('foo', ConditionOperator::Equal, 'bar'));
        $groupOr->add(new Condition('foo', ConditionOperator::Equal, 'baz'));
        $entityRequest->setFilter($groupOr);

        $condition = new Condition('foo', ConditionOperator::Equal, 'barbaz');
        $entityRequest->addFilter($condition, false);
        $this->assertInstanceOf(Group::class, $entityRequest->getFilter());

        $this->assertTrue($groupOr === $entityRequest->getFilter());
        $this->assertCount(3, $groupOr->getConditions());
    }

    public function test_add_filter_different_operator()
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $groupOr = new Group(GroupOperator::Or);
        $groupOr->add(new Condition('foo', ConditionOperator::Equal, 'bar'));
        $groupOr->add(new Condition('foo', ConditionOperator::Equal, 'baz'));
        $entityRequest->setFilter($groupOr);

        $condition = new Condition('foo', ConditionOperator::Equal, 'barbaz');
        $entityRequest->addFilter($condition, true);
        $this->assertInstanceOf(Group::class, $entityRequest->getFilter());

        /** @var Group $groupAnd */
        $groupAnd = $entityRequest->getFilter();
        $this->assertEquals(GroupOperator::And, $groupAnd->getOperator());
        $this->assertCount(2, $groupAnd->getConditions());
        $this->assertTrue($groupOr === $groupAnd->getConditions()[0]);
        $this->assertTrue($condition === $groupAnd->getConditions()[1]);
    }

    public function test_add_filter_empty_array()
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $entityRequest->addFilter([]);
        $this->assertNull($entityRequest->getFilter());
    }

    public function test_add_filter_array_invalid()
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $this->expectExceptionMessage('each filters element must be instance of AbstractCondition');
        $entityRequest->addFilter(['foo']);
    }

    public function test_add_sort()
    {
        $entityRequest = new EntityRequest(['entity' => 'user']);

        $entityRequest->addSort('foo');
        $entityRequest->addSort('bar', OrderDirection::Desc);

        $this->assertEquals([
            ['property' => 'foo', 'order' => OrderDirection::Asc, 'filter' => null, 'aggregation' => null],
            ['property' => 'bar', 'order' => OrderDirection::Desc, 'filter' => null, 'aggregation' => null],
        ], $entityRequest->getSort());
    }
}
