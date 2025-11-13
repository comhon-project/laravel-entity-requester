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
            'model' => 'user',
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
            'model' => 'user',
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
            'model' => 'user',
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
            'model' => 'user',
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
            'model' => 'user',
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
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'posts.foo',
                ],
            ],
        ]));
    }

    public function test_authorize_valid()
    {
        Gate::authorize(new EntityRequest([
            'model' => 'user',
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
}
