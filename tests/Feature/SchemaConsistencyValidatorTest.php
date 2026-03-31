<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use App\Models\Purchase;
use App\Models\User;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\DTOs\MorphCondition;
use Comhon\EntityRequester\DTOs\Scope;
use Comhon\EntityRequester\EntityRequest\SchemaConsistencyValidator;
use Comhon\EntityRequester\Enums\AggregationFunction;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Enums\GroupOperator;
use Comhon\EntityRequester\Enums\MathOperator;
use Comhon\EntityRequester\Enums\OrderDirection;
use Comhon\EntityRequester\Exceptions\InvalidEntityConditionException;
use Comhon\EntityRequester\Exceptions\InvalidOperatorForPropertyTypeException;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\InvalidToManySortException;
use Comhon\EntityRequester\Exceptions\MorphEntitiesRequiredException;
use Comhon\EntityRequester\Exceptions\NonTraversablePropertyException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\PropertyNotFoundException;
use Comhon\EntityRequester\Exceptions\UnknownMorphEntityException;
use Tests\TestCase;

class SchemaConsistencyValidatorTest extends TestCase
{
    private function validator(): SchemaConsistencyValidator
    {
        return app(SchemaConsistencyValidator::class);
    }

    public function test_valid_condition()
    {
        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Condition('email', ConditionOperator::Equal, 'john@example.com'));
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_valid_scope()
    {
        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('foo', ['bar', 123.321, Fruit::Apple->value]));
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_valid_scope_without_parameters()
    {
        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('validated'));
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_valid_morph_condition()
    {
        $entityRequest = new EntityRequest(Purchase::class);
        $entityRequest->setFilter(new MorphCondition(
            'buyer',
            EntityConditionOperator::Has,
            ['user'],
            new Condition('email', ConditionOperator::Equal, 'john@example.com'),
        ));
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_valid_entity_condition_relationship()
    {
        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition(
            'posts',
            EntityConditionOperator::Has,
            new Condition('name', ConditionOperator::Equal, 'test'),
        ));
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_valid_group()
    {
        $entityRequest = new EntityRequest(User::class);
        $group = new Group(GroupOperator::And);
        $group->add(new Condition('email', ConditionOperator::Equal, 'john@example.com'));
        $group->add(new Scope('validated'));
        $entityRequest->setFilter($group);
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_scalar_operator_on_array_property_throws()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);
        $this->expectExceptionMessage("Condition operator '=' is not valid for 'array' property type, must be one of [contains, not_contains]");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Condition('favorite_fruits', ConditionOperator::Equal, 'apple'));
        $this->validator()->validate($entityRequest);
    }

    public function test_contains_on_non_array_property_throws()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);
        $this->expectExceptionMessage("Condition operator 'contains' is not valid for 'string' property type");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Condition('name', ConditionOperator::Contains, 'john'));
        $this->validator()->validate($entityRequest);
    }

    public function test_object_type_invalid_operator()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);
        $this->expectExceptionMessage("Condition operator '=' is not valid for 'object' property type, must be one of [has_key, has_not_key]");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Condition('metadata', ConditionOperator::Equal, 'test'));
        $this->validator()->validate($entityRequest);
    }

    public function test_entity_condition_on_morph_to_throws()
    {
        $this->expectException(MorphEntitiesRequiredException::class);

        $entityRequest = new EntityRequest(Purchase::class);
        $entityRequest->setFilter(new EntityCondition('buyer', EntityConditionOperator::Has));
        $this->validator()->validate($entityRequest);
    }

    public function test_unknown_morph_entity_throws()
    {
        $this->expectException(UnknownMorphEntityException::class);
        $this->expectExceptionMessage("'nonexistent'");

        $entityRequest = new EntityRequest(Purchase::class);
        $entityRequest->setFilter(new MorphCondition(
            'buyer',
            EntityConditionOperator::Has,
            ['nonexistent'],
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_unknown_scope_throws()
    {
        $this->expectException(NotScopableException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('nonexistent'));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_invalid_parameter_type_throws()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('age', ['not_an_int']));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_invalid_enum_value_throws()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('foo', ['bar', 123.321, 'invalid_fruit']));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_nullable_parameter_accepts_null()
    {
        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('carbon', [null]));
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_scope_non_nullable_parameter_rejects_null()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('dateTime', [null]));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_inside_object_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage('Scopes are not supported inside object entity conditions');

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition(
            'metadata',
            EntityConditionOperator::Has,
            new Scope('validated'),
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_morph_inside_object_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage('MorphCondition is not supported inside object entity conditions');

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition(
            'metadata',
            EntityConditionOperator::Has,
            new MorphCondition('buyer', EntityConditionOperator::Has, ['user']),
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_entity_condition_on_scalar_property_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Property 'email' does not support entity condition filtering");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition('email', EntityConditionOperator::Has));
        $this->validator()->validate($entityRequest);
    }

    public function test_entity_condition_has_not_with_filter_on_object_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Operator 'has_not' with filter is not supported on object property 'metadata'");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition(
            'metadata',
            EntityConditionOperator::HasNot,
            new Condition('label', ConditionOperator::Equal, 'test'),
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_entity_condition_count_on_object_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Options 'count_operator' and 'count' are not supported on object property 'metadata'");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition(
            'metadata',
            EntityConditionOperator::Has,
            null,
            MathOperator::GreaterThanOrEqual,
            2,
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_morph_condition_on_non_morph_to_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Property 'posts' is not a morph_to relationship");

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new MorphCondition(
            'posts',
            EntityConditionOperator::Has,
            ['post'],
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_condition_unknown_property_throws()
    {
        $this->expectException(PropertyNotFoundException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Condition('nonexistent', ConditionOperator::Equal, 'test'));
        $this->validator()->validate($entityRequest);
    }

    public function test_entity_condition_unknown_property_throws()
    {
        $this->expectException(PropertyNotFoundException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new EntityCondition('nonexistent', EntityConditionOperator::Has));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_too_many_parameters_throws()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('validated', ['extra']));
        $this->validator()->validate($entityRequest);
    }

    public function test_sort_unknown_property_throws()
    {
        $this->expectException(PropertyNotFoundException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->addSort('nonexistent');
        $this->validator()->validate($entityRequest);
    }

    public function test_sort_non_traversable_property_throws()
    {
        $this->expectException(NonTraversablePropertyException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->addSort('email.something');
        $this->validator()->validate($entityRequest);
    }

    public function test_sort_to_many_without_aggregation_throws()
    {
        $this->expectException(InvalidToManySortException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->addSort('posts.name');
        $this->validator()->validate($entityRequest);
    }

    public function test_sort_through_morph_to_throws()
    {
        $this->expectException(NonTraversablePropertyException::class);
        $this->expectExceptionMessage("Property 'buyer' is not traversable");

        $entityRequest = new EntityRequest(Purchase::class);
        $entityRequest->addSort('buyer.email');
        $this->validator()->validate($entityRequest);
    }

    public function test_sort_unknown_intermediate_property_throws()
    {
        $this->expectException(PropertyNotFoundException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->addSort('posts.unknown.foo', OrderDirection::Asc, null, AggregationFunction::Min);
        $this->validator()->validate($entityRequest);
    }

    public function test_sort_with_valid_filter()
    {
        $entityRequest = new EntityRequest(User::class);
        $entityRequest->addSort(
            'posts.name',
            OrderDirection::Asc,
            new Condition('name', ConditionOperator::Equal, 'test'),
            AggregationFunction::Min,
        );
        $this->validator()->validate($entityRequest);
        $this->assertTrue(true);
    }

    public function test_morph_condition_unknown_property_throws()
    {
        $this->expectException(PropertyNotFoundException::class);

        $entityRequest = new EntityRequest(Purchase::class);
        $entityRequest->setFilter(new MorphCondition(
            'nonexistent',
            EntityConditionOperator::Has,
            ['user'],
        ));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_missing_parameter_throws()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('foo', ['bar']));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_boolean_parameter_wrong_type_throws()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('bool', ['not_a_bool']));
        $this->validator()->validate($entityRequest);
    }

    public function test_scope_datetime_parameter_wrong_type_throws()
    {
        $this->expectException(InvalidScopeParametersException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->setFilter(new Scope('dateTime', [123]));
        $this->validator()->validate($entityRequest);
    }

    public function test_multiple_to_many_unsafe_aggregation_throws()
    {
        $this->expectException(InvalidToManySortException::class);

        $entityRequest = new EntityRequest(User::class);
        $entityRequest->addSort('posts.name', OrderDirection::Asc, null, AggregationFunction::Count);
        $entityRequest->addSort('friends.email', OrderDirection::Asc, null, AggregationFunction::Sum);
        $this->validator()->validate($entityRequest);
    }
}
