<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Facades\Gate;
use Tests\TestCase;

class RequestGateTest extends TestCase
{
    public function test_authorize_not_filtrable()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'relationship_condition',
                'operator' => 'Has',
                'property' => 'foo',
            ],
        ]));
    }

    public function test_authorize_not_sortable()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'sort' => [
                ['property' => 'foo'],
            ],
        ]));
    }

    public function test_authorize_not_scopable()
    {
        $this->expectException(NotScopableException::class);
        $this->expectExceptionMessage("scope 'foobar' is not valid");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foobar',
            ],
        ]));
    }

    public function test_authorize_relationship_sort_not_sortable_relation()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
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
                                'type' => 'relationship_condition',
                                'operator' => 'Has',
                                'property' => 'purchases',
                                'filter' => [
                                    'type' => 'relationship_condition',
                                    'operator' => 'Has',
                                    'property' => 'buyer',
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

    public function test_authorize_dot_notation_uses_root_property()
    {
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'metadata.address.city',
                'value' => 'Paris',
            ],
        ]));

        $this->assertTrue(true);
    }

    public function test_authorize_dot_notation_not_filtrable_root()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'password' is not filtrable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'password.something',
                'value' => 'test',
            ],
        ]));
    }

    public function test_authorize_object_sort_dot_notation()
    {
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'sort' => [
                ['property' => 'password.something'],
            ],
        ]));
    }

    public function test_authorize_mixed_relation_object_sort()
    {
        Gate::authorize(new EntityRequest([
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

    public function test_authorize_dot_notation_not_filtrable_nonexistent_inline_entity_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'metadata.foo',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_authorize_dot_notation_not_filtrable_existing_inline_entity_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'secret' is not filtrable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'metadata.secret',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_authorize_dot_notation_not_filtrable_nested_inline_entity_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'metadata.address.foo',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_authorize_object_sort_not_sortable_nonexistent_inline_entity_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
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
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.address.foo'],
            ],
        ]));
    }

    public function test_authorize_dot_notation_not_filtrable_through_non_object_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'something' is not filtrable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'metadata.label.something',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_authorize_dot_notation_not_filtrable_missing_inline_request_schema()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'something' is not filtrable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'metadata.extra.something',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_authorize_object_sort_not_sortable_through_non_object_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'something' is not sortable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.label.something'],
            ],
        ]));
    }

    public function test_authorize_object_sort_not_sortable_missing_inline_request_schema()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'something' is not sortable");
        Gate::authorize(new EntityRequest([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.extra.something'],
            ],
        ]));
    }

    public function test_authorize_mixed_relation_object_sort_not_sortable_inline()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        Gate::authorize(new EntityRequest([
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
}
