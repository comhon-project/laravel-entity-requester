<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use App\Models\User;
use Comhon\EntityRequester\Database\EntityRequestBuilder;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\InvalidSortPropertyException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotScopableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EntityRequesterBuilderTest extends TestCase
{
    public function getRawSqlAccordingDriver(string $rawSql): string
    {
        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver != 'pgsql' && $driver != 'sqlite') {
            $rawSql = str_replace('"', '`', $rawSql);
        }
        if ($driver == 'pgsql') {
            $rawSql = str_replace(['" LIKE ', '" NOT LIKE '], ['"::text LIKE ', '"::text NOT LIKE '], $rawSql);
        }

        return $rawSql;
    }

    public function test_build_entity_request_only_class_name()
    {
        $query = EntityRequestBuilder::fromInputs([], User::class);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "users" order by "name" asc, "first_name" asc');
        $this->assertEquals($rawSql, $query->toRawSql());
    }

    public function test_build_entity_request_only_model()
    {
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "users" order by "name" asc, "first_name" asc');
        $this->assertEquals($rawSql, $query->toRawSql($rawSql));
    }

    public function test_build_entity_request_default_sort_by_model_key()
    {
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'post',
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "posts" order by "id" asc');
        $this->assertEquals($rawSql, $query->toRawSql());
    }

    public function test_build_entity_request_not_filtrable()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'foo' is not filtrable");
        EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'filter' => [
                'type' => 'condition',
                'property' => 'foo',
                'value' => 'bar',
            ],
        ]);
    }

    public function test_build_entity_request_not_sortable()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'sort' => [
                ['property' => 'foo'],
            ],
        ]);
    }

    public function test_build_entity_request_not_scopable()
    {
        $this->expectException(NotScopableException::class);
        $this->expectExceptionMessage("scope 'foobar' is not valid");
        EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foobar',
            ],
        ]);
    }

    public function test_build_entity_request_invalid_scope()
    {
        $this->expectException(InvalidScopeParametersException::class);
        $this->expectExceptionMessage("invalid 'foo' scope parameters");
        EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foo',
            ],
        ]);
    }

    public function test_build_entity_request_invalid_scope_parameter()
    {
        $this->expectException(InvalidScopeParametersException::class);
        $this->expectExceptionMessage("invalid 'foo' scope parameters");
        EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foo',
                'parameters' => [[], 123.321, Fruit::Apple->value],
            ],
        ]);
    }

    public function test_build_entity_request_invalid_relationship_sort_property()
    {
        $this->expectException(InvalidSortPropertyException::class);
        $this->expectExceptionMessage("Invalid sort property 'posts.foo.bar'");
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'posts.foo.bar',
                ],
            ],
        ]);
    }

    public function test_build_entity_request_relationship_sort_not_sortable_relation()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'foo.bar',
                ],
            ],
        ]);
    }

    public function test_build_entity_request_relationship_sort_not_sortable_property()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'foo' is not sortable");
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'posts.foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_request_relationship_sort_not_managed_relation()
    {
        $this->expectExceptionMessage("relation not managed : Illuminate\Database\Eloquent\Relations\BelongsToMany");
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'friends.foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_request_valid()
    {
        $query = EntityRequestBuilder::fromInputs([
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
                                'property' => 'posts',
                            ],
                            [
                                'type' => 'relationship_condition',
                                'operator' => 'Has_Not',
                                'property' => 'friends',
                                'filter' => [
                                    'type' => 'condition',
                                    'operator' => '=',
                                    'property' => 'first_name',
                                    'value' => 'john',
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
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'tag',
                        'value' => 'public',
                    ],
                ],
                [
                    'property' => 'birth_date',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select "users".* from "users" left join "posts" on "users"."id" = "posts"."owner_id" where (("posts"."tag" = \'public\') or "posts"."owner_id" is null) and ("users"."email" = \'john.doe@gmail.com\' and ("comment" = \'foo-123.321-apple\') and (exists (select * from "posts" where "users"."id" = "posts"."owner_id") or not exists (select * from "users" as "laravel_reserved_0" inner join "friendships" on "laravel_reserved_0"."id" = "friendships"."to_id" where "users"."id" = "friendships"."from_id" and "laravel_reserved_0"."first_name" = \'john\'))) group by "users"."id" order by MAX(posts.name) DESC, "birth_date" asc');
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_condition_operators(bool $and)
    {
        $operator = $and ? 'and' : 'or';
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'filter' => [
                'type' => 'group',
                'operator' => $operator,
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'email',
                        'value' => 'john.doe@gmail.com',
                    ],
                    [
                        'type' => 'condition',
                        'operator' => '<>',
                        'property' => 'email',
                        'value' => 'john.doe@gmail.com',
                    ],
                    [
                        'type' => 'condition',
                        'operator' => '>',
                        'property' => 'age',
                        'value' => 32,
                    ],
                    [
                        'type' => 'condition',
                        'operator' => '>=',
                        'property' => 'age',
                        'value' => 32,
                    ],
                    [
                        'type' => 'condition',
                        'operator' => '<',
                        'property' => 'age',
                        'value' => 32,
                    ],
                    [
                        'type' => 'condition',
                        'operator' => '<=',
                        'property' => 'age',
                        'value' => 32,
                    ],
                    [
                        'type' => 'condition',
                        'operator' => 'like',
                        'property' => 'email',
                        'value' => '%@gmail.com',
                    ],
                    [
                        'type' => 'condition',
                        'operator' => 'not like',
                        'property' => 'email',
                        'value' => '%@gmail.com',
                    ],
                    [
                        'type' => 'condition',
                        'operator' => 'in',
                        'property' => 'age',
                        'value' => [10, 20],
                    ],
                    [
                        'type' => 'condition',
                        'operator' => 'not in',
                        'property' => 'age',
                        'value' => [30, 40],
                    ],
                    [
                        'type' => 'group',
                        'operator' => 'and',
                        'filters' => [
                            [
                                'type' => 'condition',
                                'operator' => '=',
                                'property' => 'age',
                                'value' => 25,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver("select * from \"users\" where (\"users\".\"email\" = 'john.doe@gmail.com' $operator \"users\".\"email\" <> 'john.doe@gmail.com' $operator \"users\".\"age\" > 32 $operator \"users\".\"age\" >= 32 $operator \"users\".\"age\" < 32 $operator \"users\".\"age\" <= 32 $operator \"users\".\"email\" LIKE '%@gmail.com' $operator \"users\".\"email\" NOT LIKE '%@gmail.com' $operator \"users\".\"age\" in (10, 20) $operator \"users\".\"age\" not in (30, 40) $operator (\"users\".\"age\" = 25)) order by \"name\" asc, \"first_name\" asc");
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_scope_attribute()
    {
        if (! class_exists(Scope::class)) {
            $this->assertTrue(true);

            return;
        }

        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'filter' => [
                'type' => 'group',
                'operator' => 'and',
                'filters' => [
                    [
                        'type' => 'scope',
                        'name' => 'age',
                        'parameters' => [25],
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "users" where (("age" = 25)) order by "name" asc, "first_name" asc');
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_one_or_many_sort()
    {
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'user',
            'sort' => [
                [
                    'property' => 'purchases.amount',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select "users".* from "users" left join "purchases" on "users"."id" = "purchases"."buyer_id" where (("purchases"."buyer_type" = \'user\') or "purchases"."buyer_id" is null) group by "users"."id" order by MAX(purchases.amount) ASC');
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_belongs_to_sort()
    {
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'post',
            'sort' => [
                [
                    'property' => 'owner.email',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select "posts".* from "posts" left join "users" on "posts"."owner_id" = "users"."id" group by "posts"."id" order by MAX(users.email) ASC');
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_to_sort()
    {
        $this->expectExceptionMessage("relation not managed : Illuminate\Database\Eloquent\Relations\MorphTo");
        $query = EntityRequestBuilder::fromInputs([
            'model' => 'purchase',
            'sort' => [
                [
                    'property' => 'buyer.email',
                ],
            ],
        ]);
    }
}
