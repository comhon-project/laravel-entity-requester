<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Facades\AccessValidator;
use Tests\TestCase;

class AcessValidatorTest extends TestCase
{
    public function test_access_validator_not_filtrable()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        AccessValidator::validate(new EntityRequest([
            'model' => 'user',
            'filter' => [
                'type' => 'condition',
                'property' => 'foo',
                'value' => 'bar',
            ],
        ]));
    }

    public function test_access_validator_not_filtrable_relation()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        AccessValidator::validate(new EntityRequest([
            'model' => 'user',
            'filter' => [
                'type' => 'relationship_condition',
                'operator' => 'Has',
                'property' => 'foo',
            ],
        ]));
    }

    public function test_access_validator_not_sortable()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        AccessValidator::validate(new EntityRequest([
            'model' => 'user',
            'sort' => [
                ['property' => 'foo'],
            ],
        ]));
    }

    public function test_access_validator_not_scopable()
    {
        $this->expectException(NotScopableException::class);
        $this->expectExceptionMessage("scope 'foobar' is not valid");
        AccessValidator::validate(new EntityRequest([
            'model' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foobar',
            ],
        ]));
    }

    public function test_access_validator_relationship_sort_not_sortable_relation()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        AccessValidator::validate(new EntityRequest([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'foo.bar',
                ],
            ],
        ]));
    }

    public function test_access_validator_relationship_sort_not_sortable_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        AccessValidator::validate(new EntityRequest([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'posts.foo',
                ],
            ],
        ]));
    }

    public function test_access_validator_valid()
    {
        AccessValidator::validate(new EntityRequest([
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
