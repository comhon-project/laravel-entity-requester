<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use App\Models\Purchase;
use App\Models\User;
use Comhon\EntityRequester\Database\AliasCounter;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Exceptions\InvalidEntityConditionException;
use Comhon\EntityRequester\Exceptions\InvalidOperatorForPropertyTypeException;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\InvalidToManySortException;
use Comhon\EntityRequester\Exceptions\NotFiltrableException;
use Comhon\EntityRequester\Exceptions\NotSortableException;
use Comhon\EntityRequester\Exceptions\NotSupportedOperatorException;
use Comhon\EntityRequester\Exceptions\PropertyNotFoundException;
use Comhon\EntityRequester\Facades\QueryBuilder;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AliasCounter::reset();
    }

    public function test_build_entity_request_only_class_name()
    {
        $query = QueryBuilder::fromInputs([], User::class);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "users" order by "name" asc, "first_name" asc');
        $this->assertEquals($rawSql, $query->toRawSql());
    }

    public function test_build_entity_request_only_model()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "users" order by "name" asc, "first_name" asc');
        $this->assertEquals($rawSql, $query->toRawSql($rawSql));
    }

    public function test_build_entity_request_default_sort_by_model_key()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'post',
        ]);

        $rawSql = $this->getRawSqlAccordingDriver('select * from "posts" order by "id" asc');
        $this->assertEquals($rawSql, $query->toRawSql());
    }

    public function test_build_entity_request_invalid_scope()
    {
        $this->expectException(InvalidScopeParametersException::class);
        $this->expectExceptionMessage("invalid 'foo' scope parameters");
        QueryBuilder::fromInputs([
            'entity' => 'user',
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
        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'scope',
                'name' => 'foo',
                'parameters' => [[], 123.321, Fruit::Apple->value],
            ],
        ]);
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_ilike_supported(bool $not)
    {
        config(['database.default' => 'pgsql']);
        $operator = $not ? ConditionOperator::NotIlike : ConditionOperator::Ilike;
        $entityRequest = new EntityRequest(null, User::class);
        $entityRequest->setFilter(new Condition('email', $operator, 'gmail'));

        $query = QueryBuilder::fromEntityRequest($entityRequest);

        $sqlOperator = app(ConditionOperatorManagerInterface::class)->getSqlOperator($operator);
        $sql = 'select * from "users" where "users"."email"::text '.$sqlOperator.' ? order by "name" asc, "first_name" asc';
        $this->assertEquals($sql, $query->toSql());
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_ilike_not_supported(bool $not)
    {
        config(['database.default' => 'mysql']);
        $operator = $not ? ConditionOperator::NotIlike : ConditionOperator::Ilike;
        $entityRequest = new EntityRequest(null, User::class);
        $entityRequest->setFilter(new Condition('email', $operator, 'gmail'));

        $this->expectException(NotSupportedOperatorException::class);
        $this->expectExceptionMessage("Not supported condition operator '{$operator->value}', must be one of [=, <>, <, <=, >, >=, in, not_in, like, not_like, contains, not_contains, has_key, has_not_key]");
        QueryBuilder::fromEntityRequest($entityRequest);
    }

    public function test_build_entity_request_valid()
    {
        $query = QueryBuilder::fromInputs([
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
                                'property' => 'posts',
                            ],
                            [
                                'type' => 'entity_condition',
                                'operator' => 'Has',
                                'property' => 'posts',
                                'count_operator' => '<',
                                'count' => 5,
                            ],
                            [
                                'type' => 'entity_condition',
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
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "posts" as "alias_posts_1" on "users"."id" = "alias_posts_1"."owner_id" '.
            'and ("alias_posts_1"."name" = \'public\') '.
            'where ("users"."email" = \'john.doe@gmail.com\' '.
            'and ("comment" = \'foo-123.321-apple\') '.
            'and (exists (select * from "posts" where "users"."id" = "posts"."owner_id") '.
            'or (select count(*) from "posts" where "users"."id" = "posts"."owner_id") < 5 '.
            'or not exists (select * from "users" as "laravel_reserved_0" '.
            'inner join "friendships" on "laravel_reserved_0"."id" = "friendships"."to_id" '.
            'where "users"."id" = "friendships"."from_id" and "laravel_reserved_0"."first_name" = \'john\'))) '.
            'group by "users"."id" order by max("alias_posts_1"."name") desc, "birth_date" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_condition_operators(bool $and)
    {
        $operator = $and ? 'and' : 'or';
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
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
                        'operator' => 'not_like',
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
                        'operator' => 'not_in',
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

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "users" where ('.
            "\"users\".\"email\" = 'john.doe@gmail.com' $operator ".
            "\"users\".\"email\" <> 'john.doe@gmail.com' $operator ".
            "\"users\".\"age\" > 32 $operator ".
            "\"users\".\"age\" >= 32 $operator ".
            "\"users\".\"age\" < 32 $operator ".
            "\"users\".\"age\" <= 32 $operator ".
            "\"users\".\"email\" like '%@gmail.com' $operator ".
            "\"users\".\"email\" not like '%@gmail.com' $operator ".
            "\"users\".\"age\" in (10, 20) $operator ".
            "\"users\".\"age\" not in (30, 40) $operator ".
            '("users"."age" = 25)'.
            ') order by "name" asc, "first_name" asc'
        );
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

        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
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

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "users" '.
            'where (("age" = 25)) '.
            'order by "name" asc, "first_name" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_morph_to_filter()
    {
        Purchase::factory()->for(User::factory(), 'buyer')->create();

        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'Has',
                'property' => 'buyer',
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'first_name',
                    'value' => 'john',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "purchases" '.
            'where (("purchases"."buyer_type" = \'user\' '.
            'and exists (select * from "users" '.
            'where "purchases"."buyer_id" = "users"."id" '.
            'and "users"."first_name" = \'john\'))) '.
            'order by "id" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_morph_condition()
    {
        Purchase::factory()->for(User::factory(), 'buyer')->create();

        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'Has',
                'property' => 'buyer',
                'entities' => ['user'],
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'first_name',
                    'value' => 'john',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "purchases" '.
            'where (("purchases"."buyer_type" = \'user\' '.
            'and exists (select * from "users" '.
            'where "purchases"."buyer_id" = "users"."id" '.
            'and "users"."first_name" = \'john\'))) '.
            'order by "id" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_morph_condition_without_filter()
    {
        Purchase::factory()->for(User::factory(), 'buyer')->create();

        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'Has',
                'property' => 'buyer',
                'entities' => ['user'],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "purchases" '.
            'where (("purchases"."buyer_type" = \'user\' '.
            'and exists (select * from "users" '.
            'where "purchases"."buyer_id" = "users"."id"))) '.
            'order by "id" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_morph_condition_doesnt_have()
    {
        Purchase::factory()->for(User::factory(), 'buyer')->create();

        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has_not',
                'property' => 'buyer',
                'entities' => ['user'],
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'first_name',
                    'value' => 'john',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "purchases" '.
            'where (("purchases"."buyer_type" = \'user\' '.
            'and not exists (select * from "users" '.
            'where "purchases"."buyer_id" = "users"."id" '.
            'and "users"."first_name" = \'john\'))) '.
            'order by "id" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_morph_condition_or_has()
    {
        Purchase::factory()->for(User::factory(), 'buyer')->create();

        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'group',
                'operator' => 'or',
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'amount',
                        'value' => 100,
                    ],
                    [
                        'type' => 'entity_condition',
                        'operator' => 'has',
                        'property' => 'buyer',
                        'entities' => ['user'],
                        'filter' => [
                            'type' => 'condition',
                            'operator' => '=',
                            'property' => 'first_name',
                            'value' => 'john',
                        ],
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "purchases" '.
            'where ("purchases"."amount" = 100 '.
            'or (("purchases"."buyer_type" = \'user\' '.
            'and exists (select * from "users" '.
            'where "purchases"."buyer_id" = "users"."id" '.
            'and "users"."first_name" = \'john\')))) '.
            'order by "id" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_request_with_morph_condition_or_doesnt_have()
    {
        Purchase::factory()->for(User::factory(), 'buyer')->create();

        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'filter' => [
                'type' => 'group',
                'operator' => 'or',
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'amount',
                        'value' => 100,
                    ],
                    [
                        'type' => 'entity_condition',
                        'operator' => 'has_not',
                        'property' => 'buyer',
                        'entities' => ['user'],
                        'filter' => [
                            'type' => 'condition',
                            'operator' => '=',
                            'property' => 'first_name',
                            'value' => 'john',
                        ],
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "purchases" '.
            'where ("purchases"."amount" = 100 '.
            'or (("purchases"."buyer_type" = \'user\' '.
            'and not exists (select * from "users" '.
            'where "purchases"."buyer_id" = "users"."id" '.
            'and "users"."first_name" = \'john\')))) '.
            'order by "id" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_one_or_many_sort_with_filter()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'purchases.amount',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'entity_condition',
                        'operator' => 'has',
                        'property' => 'tags',
                        'filter' => [
                            'type' => 'condition',
                            'operator' => '=',
                            'property' => 'name',
                            'value' => 'foo',
                        ],
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "purchases" as "alias_purchases_1" '.
            'on "users"."id" = "alias_purchases_1"."buyer_id" '.
            'and "alias_purchases_1"."buyer_type" = \'user\' '.
            'where exists ('.
                'select * from "tags" '.
                'inner join "taggables" on "tags"."id" = "taggables"."tag_id" '.
                'where "alias_purchases_1"."id" = "taggables"."taggable_id" '.
                'and "taggables"."taggable_type" = \'purchase\' '.
                'and "tags"."name" = \'foo\''.
            ') '.
            'or "alias_purchases_1"."id" is null '.
            'group by "users"."id" '.
            'order by count("alias_purchases_1"."amount") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_belongs_to_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'post',
            'sort' => [
                [
                    'property' => 'owner.email',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "posts".* from "posts" '.
            'left join "users" as "alias_users_1" '.
            'on "posts"."owner_id" = "alias_users_1"."id" '.
            'order by "alias_users_1"."email" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_belongs_to_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'post',
            'sort' => [
                [
                    'property' => 'owner.email',
                    'filter' => [
                        'type' => 'scope',
                        'name' => 'validated',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "posts".* from "posts" '.
            'left join (select * from "users" where ("name" = \'validated\')) as "alias_sub_users_1" '.
            'on "posts"."owner_id" = "alias_sub_users_1"."id" '.
            'order by "alias_sub_users_1"."email" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_has_one_or_many_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.name',
                    'aggregation' => 'count',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "posts" as "alias_posts_1" '.
            'on "users"."id" = "alias_posts_1"."owner_id" '.
            'group by "users"."id" '.
            'order by count("alias_posts_1"."name") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_has_one_or_many_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'publicPosts.name',
                    'aggregation' => 'count',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join (select * from "posts" where "posts"."name" = \'public\') as "alias_sub_posts_1" '.
            'on "users"."id" = "alias_sub_posts_1"."owner_id" '.
            'group by "users"."id" '.
            'order by count("alias_sub_posts_1"."name") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_one_or_many_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'purchases.amount',
                    'aggregation' => 'sum',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "purchases" as "alias_purchases_1" '.
            'on "users"."id" = "alias_purchases_1"."buyer_id" '.
            'and "alias_purchases_1"."buyer_type" = \'user\' '.
            'group by "users"."id" '.
            'order by sum("alias_purchases_1"."amount") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_one_or_many_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'purchases.amount',
                    'aggregation' => 'sum',
                    'filter' => [
                        'type' => 'scope',
                        'name' => 'expensive',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join (select * from "purchases" where "purchases"."buyer_type" = \'user\' '.
            'and ("purchases"."amount" >= 1000)) as "alias_sub_purchases_1" '.
            'on "users"."id" = "alias_sub_purchases_1"."buyer_id" '.
            'group by "users"."id" '.
            'order by sum("alias_sub_purchases_1"."amount") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_belongs_to_many_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'friends.id',
                    'aggregation' => 'count',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "friendships" as "alias_friendships_2" '.
            'on "users"."id" = "alias_friendships_2"."from_id" '.
            'inner join "users" as "alias_users_1" '.
            'on "alias_users_1"."id" = "alias_friendships_2"."to_id" '.
            'group by "users"."id" '.
            'order by count("alias_users_1"."id") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_belongs_to_many_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'friends.id',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'scope',
                        'name' => 'validated',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join (select "users".*, "friendships"."from_id" as "alias_from_id_2" '.
            'from "users" inner join "friendships" on "users"."id" = "friendships"."to_id" '.
            'where ("name" = \'validated\')) as "alias_sub_users_1" '.
            'on "users"."id" = "alias_sub_users_1"."alias_from_id_2" '.
            'group by "users"."id" '.
            'order by count("alias_sub_users_1"."id") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_to_many_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'post',
            'sort' => [
                [
                    'property' => 'tags.id',
                    'aggregation' => 'avg',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "posts".* from "posts" '.
            'left join "taggables" as "alias_taggables_2" '.
            'on "posts"."id" = "alias_taggables_2"."taggable_id" '.
            'and "alias_taggables_2"."taggable_type" = \'post\' '.
            'inner join "tags" as "alias_tags_1" '.
            'on "alias_tags_1"."id" = "alias_taggables_2"."tag_id" '.
            'group by "posts"."id" '.
            'order by avg("alias_tags_1"."id") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_to_many_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'post',
            'sort' => [
                [
                    'property' => 'publicTags.id',
                    'aggregation' => 'avg',
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "posts".* from "posts" '.
            'left join (select "tags".*, "taggables"."taggable_id" as "alias_taggable_id_2" '.
            'from "tags" inner join "taggables" on "tags"."id" = "taggables"."tag_id" '.
            'and "taggables"."taggable_type" = \'post\' where "tags"."name" = \'public\') as "alias_sub_tags_1" '.
            'on "posts"."id" = "alias_sub_tags_1"."alias_taggable_id_2" '.
            'group by "posts"."id" '.
            'order by avg("alias_sub_tags_1"."id") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morphed_by_many_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'tag',
            'sort' => [
                [
                    'property' => 'posts.name',
                    'aggregation' => 'min',
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'name',
                        'value' => 'public',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "tags".* from "tags" '.
            'left join "taggables" as "alias_taggables_2" '.
            'on "tags"."id" = "alias_taggables_2"."tag_id" '.
            'and "alias_taggables_2"."taggable_type" = \'post\' '.
            'inner join "posts" as "alias_posts_1" '.
            'on "alias_posts_1"."id" = "alias_taggables_2"."taggable_id" '.
            'and "alias_posts_1"."name" = \'public\' '.
            'group by "tags"."id" '.
            'order by min("alias_posts_1"."name") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morphed_by_many_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'tag',
            'sort' => [
                [
                    'property' => 'posts.name',
                    'aggregation' => 'min',
                    'filter' => [
                        'type' => 'scope',
                        'name' => 'validated',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "tags".* from "tags" '.
            'left join (select "posts".*, "taggables"."tag_id" as "alias_tag_id_2" '.
            'from "posts" inner join "taggables" on "posts"."id" = "taggables"."taggable_id" '.
            'and "taggables"."taggable_type" = \'post\' where ("posts"."name" = \'validated\')) as "alias_sub_posts_1" '.
            'on "tags"."id" = "alias_sub_posts_1"."alias_tag_id_2" '.
            'group by "tags"."id" '.
            'order by min("alias_sub_posts_1"."name") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_has_many_through_sort_simple()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'childrenPosts.name',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'name',
                        'value' => 'foo',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "users" as "alias_users_1" '.
            'on "users"."id" = "alias_users_1"."parent_id" '.
            'inner join "posts" as "alias_posts_2" '.
            'on "alias_users_1"."id" = "alias_posts_2"."owner_id" '.
            'and "alias_posts_2"."name" = \'foo\' '.
            'group by "users"."id" '.
            'order by count("alias_posts_2"."name") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_has_many_through_sort_complex()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'childrenPosts.name',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'scope',
                        'name' => 'validated',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join (select "posts".*, "users"."parent_id" as "alias_parent_id_2" '.
            'from "posts" inner join "users" on "users"."id" = "posts"."owner_id" '.
            'where ("posts"."name" = \'validated\')) as "alias_sub_posts_1" '.
            'on "users"."id" = "alias_sub_posts_1"."alias_parent_id_2" '.
            'group by "users"."id" '.
            'order by count("alias_sub_posts_1"."name") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_relationship_morph_to_sort()
    {
        $this->expectExceptionMessage('MorphTo relations not managed for sorting');
        $query = QueryBuilder::fromInputs([
            'entity' => 'purchase',
            'sort' => [
                [
                    'property' => 'buyer.email',
                ],
            ],
        ]);
    }

    public function test_build_entity_relationship_missing_arragration()
    {
        $this->expectExceptionMessage('Invalid "to many" sort on property \'friends.id\', it must have aggregation function');
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'friends.id',
                ],
            ],
        ]);
    }

    public function test_build_entity_sort_nested_relations_without_subquery()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'friends.posts.tags.id',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'condition',
                        'operator' => '=',
                        'property' => 'name',
                        'value' => 'foo',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "friendships" as "alias_friendships_2" on "users"."id" = "alias_friendships_2"."from_id" '.
            'inner join "users" as "alias_users_1" on "alias_users_1"."id" = "alias_friendships_2"."to_id" '.
            'inner join "posts" as "alias_posts_3" on "alias_users_1"."id" = "alias_posts_3"."owner_id" '.
            'inner join "taggables" as "alias_taggables_5" on "alias_posts_3"."id" = "alias_taggables_5"."taggable_id" '.
            'and "alias_taggables_5"."taggable_type" = \'post\' '.
            'inner join "tags" as "alias_tags_4" on "alias_tags_4"."id" = "alias_taggables_5"."tag_id" '.
            'and "alias_tags_4"."name" = \'foo\' '.
            'group by "users"."id" order by count("alias_tags_4"."id") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_contains_operators(bool $and)
    {
        $operator = $and ? 'and' : 'or';
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'group',
                'operator' => $operator,
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => 'contains',
                        'property' => 'favorite_fruits',
                        'value' => Fruit::Apple->value,
                    ],
                    [
                        'type' => 'condition',
                        'operator' => 'not_contains',
                        'property' => 'favorite_fruits',
                        'value' => Fruit::Orange->value,
                    ],
                ],
            ],
        ]);

        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver === 'sqlite') {
            $containsSql = 'exists (select 1 from json_each("users"."favorite_fruits") where "json_each"."value" is \''.Fruit::Apple->value.'\')';
            $notContainsSql = 'not exists (select 1 from json_each("users"."favorite_fruits") where "json_each"."value" is \''.Fruit::Orange->value.'\')';
        } elseif ($driver === 'pgsql') {
            $containsSql = '("users"."favorite_fruits")::jsonb @> \'"'.Fruit::Apple->value.'"\'';
            $notContainsSql = 'not ("users"."favorite_fruits")::jsonb @> \'"'.Fruit::Orange->value.'"\'';
        } else {
            $containsSql = 'json_contains(`users`.`favorite_fruits`, \'\"'.Fruit::Apple->value.'\"\')';
            $notContainsSql = 'not json_contains(`users`.`favorite_fruits`, \'\"'.Fruit::Orange->value.'\"\')';
        }

        if ($driver !== 'sqlite' && $driver !== 'pgsql') {
            $rawSql = 'select * from `users` where ('.$containsSql.' '.$operator.' '.$notContainsSql.') order by `name` asc, `first_name` asc';
        } else {
            $rawSql = 'select * from "users" where ('.$containsSql.' '.$operator.' '.$notContainsSql.') order by "name" asc, "first_name" asc';
        }

        $this->assertEquals($rawSql, $query->toRawSql());
        $query->get();
    }

    public function test_build_entity_request_scalar_operator_on_array_property_throws()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);
        $this->expectExceptionMessage("Condition operator '=' is not valid for 'array' property type, must be one of [contains, not_contains]");

        $entityRequest = new EntityRequest(null, User::class);
        $entityRequest->setFilter(new Condition('favorite_fruits', ConditionOperator::Equal, 'apple'));
        QueryBuilder::fromEntityRequest($entityRequest);
    }

    public function test_build_entity_request_contains_on_non_array_property_throws()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);
        $this->expectExceptionMessage("Condition operator 'contains' is not valid for 'string' property type");

        $entityRequest = new EntityRequest(null, User::class);
        $entityRequest->setFilter(new Condition('name', ConditionOperator::Contains, 'john'));
        QueryBuilder::fromEntityRequest($entityRequest);
    }

    public function test_build_entity_request_entity_condition_object_nested()
    {
        $query = QueryBuilder::fromInputs([
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
        ]);

        $rawSql = $query->toRawSql();
        $this->assertStringContainsString('metadata', $rawSql);
        $this->assertStringContainsString('address', $rawSql);
        $this->assertStringContainsString('city', $rawSql);
        $this->assertStringContainsString('Paris', $rawSql);
        $query->get();
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_entity_condition_object_group(bool $and)
    {
        $operator = $and ? 'and' : 'or';
        $query = QueryBuilder::fromInputs([
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
                        'type' => 'group',
                        'operator' => $operator,
                        'filters' => [
                            [
                                'type' => 'condition',
                                'operator' => '=',
                                'property' => 'city',
                                'value' => 'Paris',
                            ],
                            [
                                'type' => 'condition',
                                'operator' => '<>',
                                'property' => 'zip',
                                'value' => '75000',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $rawSql = $query->toRawSql();
        $this->assertStringContainsString('metadata', $rawSql);
        $this->assertStringContainsString('city', $rawSql);
        $this->assertStringContainsString('Paris', $rawSql);
        $this->assertStringContainsString('zip', $rawSql);
        $this->assertStringContainsString('75000', $rawSql);
        $query->get();
    }

    public function test_build_entity_request_entity_condition_object_one_level()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'condition',
                    'operator' => 'like',
                    'property' => 'label',
                    'value' => '%test%',
                ],
            ],
        ]);

        $rawSql = $query->toRawSql();
        $this->assertStringContainsString('metadata', $rawSql);
        $this->assertStringContainsString('label', $rawSql);
        $query->get();
    }

    public function test_build_entity_request_entity_condition_object_has_without_filter()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "users" where "users"."metadata" is not null order by "name" asc, "first_name" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());
        $query->get();
    }

    public function test_build_entity_request_entity_condition_object_has_not_without_filter()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has_not',
                'property' => 'metadata',
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "users" where "users"."metadata" is null order by "name" asc, "first_name" asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());
        $query->get();
    }

    public function test_build_entity_request_entity_condition_object_has_not_with_filter()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Operator 'has_not' with filter is not supported on object property 'metadata'");
        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has_not',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'condition',
                    'operator' => '=',
                    'property' => 'label',
                    'value' => 'foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_request_entity_condition_object_with_count()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage("Options 'count_operator' and 'count' are not supported on object property 'metadata'");
        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'count' => 2,
                'count_operator' => '>=',
            ],
        ]);
    }

    public function test_build_entity_request_entity_condition_object_with_scope()
    {
        $this->expectException(InvalidEntityConditionException::class);
        $this->expectExceptionMessage('Scopes are not supported inside object entity conditions');
        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'scope',
                    'name' => 'foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_request_object_type_invalid_operator()
    {
        $this->expectException(InvalidOperatorForPropertyTypeException::class);
        $this->expectExceptionMessage("Condition operator '=' is not valid for 'object' property type, must be one of [has_key, has_not_key]");

        $entityRequest = new EntityRequest(null, User::class);
        $entityRequest->setFilter(new Condition('metadata', ConditionOperator::Equal, 'test'));
        QueryBuilder::fromEntityRequest($entityRequest);
    }

    #[DataProvider('providerBoolean')]
    public function test_build_entity_request_has_key(bool $and)
    {
        $operator = $and ? 'and' : 'or';
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'group',
                'operator' => $operator,
                'filters' => [
                    [
                        'type' => 'condition',
                        'operator' => 'has_key',
                        'property' => 'metadata',
                        'value' => 'address',
                    ],
                    [
                        'type' => 'condition',
                        'operator' => 'has_not_key',
                        'property' => 'metadata',
                        'value' => 'label',
                    ],
                ],
            ],
        ]);

        $rawSql = $query->toRawSql();
        $this->assertStringContainsString('metadata', $rawSql);
        $this->assertStringContainsString('address', $rawSql);
        $this->assertStringContainsString('label', $rawSql);
        $query->get();
    }

    public function test_build_entity_condition_property_not_found_in_schema()
    {
        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage("Property 'unknown' not found in schema");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'condition',
                'operator' => '=',
                'property' => 'unknown',
                'value' => 'bar',
            ],
        ]);
    }

    public function test_build_entity_entity_condition_property_not_found_in_schema()
    {
        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage("Property 'unknown' not found in schema");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'unknown',
            ],
        ]);
    }

    public function test_build_entity_entity_condition_scalar_property()
    {
        $this->expectException(NotFiltrableException::class);
        $this->expectExceptionMessage("Property 'email' is not filtrable");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'email',
            ],
        ]);
    }

    public function test_build_entity_object_entity_condition_filter_property_not_found()
    {
        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage("Property 'unknown' not found in schema");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'filter' => [
                'type' => 'entity_condition',
                'operator' => 'has',
                'property' => 'metadata',
                'filter' => [
                    'type' => 'entity_condition',
                    'operator' => 'has',
                    'property' => 'unknown',
                ],
            ],
        ]);
    }

    public function test_build_entity_sort_scalar_property_with_dot_notation()
    {
        $this->expectException(NotSortableException::class);
        $this->expectExceptionMessage("Property 'email' is not sortable");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'email.foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_sort_property_not_found_in_schema()
    {
        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage("Property 'unknown' not found in schema 'user'");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'unknown.foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_sort_property_not_found_in_intermediate_schema()
    {
        $this->expectException(PropertyNotFoundException::class);
        $this->expectExceptionMessage("Property 'unknown' not found in schema 'post'");

        QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.unknown.foo',
                ],
            ],
        ]);
    }

    public function test_build_entity_sort_object_json_column()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                ['property' => 'metadata.address.city'],
            ],
        ]);

        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver === 'sqlite') {
            $rawSql = 'select * from "users" order by json_extract("metadata", \'$."address"."city"\') asc';
        } elseif ($driver === 'pgsql') {
            $rawSql = 'select * from "users" order by "metadata"->\'address\'->>\'city\' asc';
        } else {
            $rawSql = 'select * from `users` order by json_unquote(json_extract(`metadata`, \'$."address"."city"\')) asc';
        }
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_sort_mixed_relation_and_object()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.owner.metadata.address.city',
                    'aggregation' => 'min',
                ],
            ],
        ]);

        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver === 'sqlite') {
            $rawSql = 'select "users".* from "users" '.
                'left join "posts" as "alias_posts_1" on "users"."id" = "alias_posts_1"."owner_id" '.
                'inner join "users" as "alias_users_2" on "alias_posts_1"."owner_id" = "alias_users_2"."id" '.
                'group by "users"."id" '.
                'order by min(json_extract("alias_users_2"."metadata", \'$."address"."city"\')) asc';
        } elseif ($driver === 'pgsql') {
            $rawSql = 'select "users".* from "users" '.
                'left join "posts" as "alias_posts_1" on "users"."id" = "alias_posts_1"."owner_id" '.
                'inner join "users" as "alias_users_2" on "alias_posts_1"."owner_id" = "alias_users_2"."id" '.
                'group by "users"."id" '.
                'order by min("alias_users_2"."metadata"->\'address\'->>\'city\') asc';
        } else {
            $rawSql = 'select `users`.* from `users` '.
                'left join `posts` as `alias_posts_1` on `users`.`id` = `alias_posts_1`.`owner_id` '.
                'inner join `users` as `alias_users_2` on `alias_posts_1`.`owner_id` = `alias_users_2`.`id` '.
                'group by `users`.`id` '.
                'order by min(json_unquote(json_extract(`alias_users_2`.`metadata`, \'$."address"."city"\'))) asc';
        }
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }

    public function test_build_entity_sort_mixed_relation_and_object_missing_aggregation()
    {
        $this->expectException(InvalidToManySortException::class);
        QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'posts.owner.metadata.address.city',
                ],
            ],
        ]);
    }

    public function test_build_entity_sort_nested_relations_with_subquery()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'friends.publicPosts.tags.id',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'scope',
                        'name' => 'validated',
                    ],
                ],
            ],
        ]);

        $rawSql = $this->getRawSqlAccordingDriver(
            'select "users".* from "users" '.
            'left join "friendships" as "alias_friendships_2" '.
            'on "users"."id" = "alias_friendships_2"."from_id" '.
            'inner join "users" as "alias_users_1" '.
            'on "alias_users_1"."id" = "alias_friendships_2"."to_id" '.
            'inner join (select * from "posts" where "posts"."name" = \'public\') as "alias_sub_posts_3" '.
            'on "alias_users_1"."id" = "alias_sub_posts_3"."owner_id" '.
            'inner join (select "tags".*, "taggables"."taggable_id" as "alias_taggable_id_5" '.
            'from "tags" inner join "taggables" on "tags"."id" = "taggables"."tag_id" '.
            'and "taggables"."taggable_type" = \'post\' where ("name" = \'validated\')) as "alias_sub_tags_4" '.
            'on "alias_sub_posts_3"."id" = "alias_sub_tags_4"."alias_taggable_id_5" '.
            'group by "users"."id" '.
            'order by count("alias_sub_tags_4"."id") asc'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }
}
