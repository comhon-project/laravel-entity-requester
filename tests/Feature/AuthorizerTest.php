<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use App\Models\Purchase;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\EntityRequest\Importer;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Exceptions\InvalidEntityConditionException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Interfaces\EntityRequestAuthorizerInterface;
use Tests\TestCase;

class AuthorizerTest extends TestCase
{
    public function test_authorize_not_filtrable()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'property' => 'foo',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_authorize_not_filtrable_relation()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'Has',
                'property' => 'foo',
            ],
        ]));
    }

    public function test_authorize_not_sortable()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'foo'],
            ],
        ]));
    }

    public function test_authorize_not_scopable()
    {
        $this->expectException(NotScopableException::class);
        $this->expectExceptionMessage("Scope 'foobar' is not valid");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foobar',
            ],
        ]));
    }

    public function test_authorize_sort_not_sortable_existing_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'password' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'password.bar',
                ],
            ],
        ]));
    }

    public function test_authorize_relationship_sort_not_sortable_relation()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'foo.bar',
                ],
            ],
        ]));
    }

    public function test_authorize_relationship_sort_not_sortable_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.foo',
                ],
            ],
        ]));
    }

    public function test_authorize_relationship_sort_not_sortable_intermediate()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'id' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.id.something',
                    'aggregation' => 'min',
                ],
            ],
        ]));
    }

    public function test_authorize_valid()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'group',
                'operator' => 'and',
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'email',
                        'value' => 'john.doe@gmail.com',
                    ],
                    [
                        'type' => 'scope',
                        'name' => 'foo',
                        'parameters' => ['foo', 123.321, Fruit::Apple->value],
                    ],
                    [
                        'type' => 'group',
                        'operator' => 'or',
                        'filters' => [
                            [
                                'type' => 'entity_condition',
                                'operator' => 'Has',
                                'property' => 'purchases',
                                'filter' => [
                                    'type' => 'entity_condition',
                                    'operator' => 'Has',
                                    'property' => 'buyer',
                                    'entities' => ['user'],
                                    'filter' => [
                                        'type' => 'condition',
                                        'operator' => '=',
                                        'property' => 'email',
                                        'value' => 'john.doe@gmail.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'sort' => [
                [
                    'property' => 'posts.name',
                    'order' => 'desc',
                    'aggregation' => 'max',
                    'filter' => [
                        'type' => 'group',
                        'operator' => 'and',
                        'filters' => [
                            [
                                'type' => 'condition',
                                'operator' => '=',
                                'property' => 'name',
                                'value' => 'public',
                            ],
                        ],
                    ],
                ],
                [
                    'property' => 'birth_date',
                ],
            ],
        ]));

        // request is valid so nothing happens
        $this->assertTrue(true);
    }

    public function test_authorize_entity_condition_object_nested()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'entity_condition',
                    'operator' => 'has',
                    'property' => 'address',
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'city',
                        'value' => 'Paris',
                    ],
                ],
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_authorize_entity_condition_object_has_without_filter()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_authorize_entity_condition_object_has_not_without_filter()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has_not',
                'property' => 'metadata',
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_authorize_entity_condition_object_not_filtrable_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'foo',
                    'value' => 'bar',
                ],
            ],
        ]));
    }

    public function test_authorize_entity_condition_object_not_filtrable_existing_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'secret' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'secret',
                    'value' => 'bar',
                ],
            ],
        ]));
    }

    public function test_authorize_entity_condition_object_not_filtrable_nested()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'entity_condition',
                    'operator' => 'has',
                    'property' => 'address',
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'foo',
                        'value' => 'bar',
                    ],
                ],
            ],
        ]));
    }

    public function test_authorize_object_sort_dot_notation()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.address.city'],
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_authorize_object_sort_not_sortable_root()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'password' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'password.something'],
            ],
        ]));
    }

    public function test_authorize_mixed_relation_object_sort()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.owner.metadata.address.city',
                    'order' => 'asc',
                    'aggregation' => 'min',
                ],
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_authorize_object_sort_not_sortable_nonexistent_inline_entity_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.foo'],
            ],
        ]));
    }

    public function test_authorize_object_sort_not_sortable_existing_inline_entity_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'secret' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.secret'],
            ],
        ]));
    }

    public function test_authorize_object_sort_not_sortable_nested_inline_entity_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.address.foo'],
            ],
        ]));
    }

    public function test_authorize_entity_condition_not_filtrable_existing_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'password' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'password',
            ],
        ]));
    }

    public function test_authorize_entity_condition_property_not_found_in_entity_schema()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'unknown' is not filtrable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'unknown',
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'foo',
                    'value' => 'bar',
                ],
            ],
        ]));
    }

    public function test_authorize_entity_condition_not_filtrable_scalar_property_with_filter()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Property 'email' does not support entity condition filtering");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'email',
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'foo',
                    'value' => 'bar',
                ],
            ],
        ]));
    }

    public function test_authorize_object_sort_not_sortable_through_non_object_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'something' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.label.something'],
            ],
        ]));
    }

    public function test_authorize_mixed_relation_object_sort_not_sortable_inline()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.owner.metadata.foo',
                    'order' => 'asc',
                    'aggregation' => 'min',
                ],
            ],
        ]));
    }

    public function test_authorize_morph_condition()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'buyer',
                'entities' => ['user'],
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'email',
                    'value' => 'john@example.com',
                ],
            ],
        ]));
        $this->assertTrue(true);
    }

    public function test_authorize_morph_condition_without_filter()
    {
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'buyer',
                'entities' => ['user'],
            ],
        ]));
        $this->assertTrue(true);
    }

    public function test_authorize_entity_condition_on_morph_to_with_filter_throws()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Property 'buyer' does not support entity condition filtering");

        $entityRequest = new EntityRequest(Purchase::class);
        $entityRequest->setFilter(
            new EntityCondition(
                'buyer',
                EntityConditionOperator::Has,
                new Condition('email', ConditionOperator::Equal, 'john@example.com'),
            )
        );
        app(EntityRequestAuthorizerInterface::class)->authorize($entityRequest);
    }

    public function test_authorize_morph_condition_invalid_entity()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Entity 'post' is not allowed for morph property 'buyer'");
        app(EntityRequestAuthorizerInterface::class)->authorize(app(Importer::class)->import([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'buyer',
                'entities' => ['post'],
            ],
        ]));
    }
}
