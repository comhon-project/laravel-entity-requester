<?php

namespace Tests\Feature\Feature;

use App\Enums\Fruit;
use App\Models\Purchase;
use App\Models\User;
use Comhon\EntityRequester\Database\AliasCounter;
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\DTOs\EntityRequest;
use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Exceptions\InvalidScopeParametersException;
use Comhon\EntityRequester\Exceptions\NotSupportedOperatorException;
use Comhon\EntityRequester\Facades\QueryBuilder;
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

        $sql = 'select * from "users" where "users"."email"::text '.$operator->value.' ? order by "name" asc, "first_name" asc';
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
        $this->expectExceptionMessage("Not supported condition operator '{$operator->value}', must be one of [=, <>, <, <=, >, >=, IN, NOT IN, LIKE, NOT LIKE]");
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
                                'type' => 'relationship_condition',
                                'operator' => 'Has',
                                'property' => 'posts',
                            ],
                            [
                                'type' => 'relationship_condition',
                                'operator' => 'Has',
                                'property' => 'posts',
                                'count_operator' => '<',
                                'count' => 5,
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
            'group by "users"."id" order by MAX("alias_posts_1"."name") DESC, "birth_date" asc'
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

        $rawSql = $this->getRawSqlAccordingDriver(
            'select * from "users" where ('.
            "\"users\".\"email\" = 'john.doe@gmail.com' $operator ".
            "\"users\".\"email\" <> 'john.doe@gmail.com' $operator ".
            "\"users\".\"age\" > 32 $operator ".
            "\"users\".\"age\" >= 32 $operator ".
            "\"users\".\"age\" < 32 $operator ".
            "\"users\".\"age\" <= 32 $operator ".
            "\"users\".\"email\" LIKE '%@gmail.com' $operator ".
            "\"users\".\"email\" NOT LIKE '%@gmail.com' $operator ".
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
                'type' => 'relationship_condition',
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

    public function test_build_entity_relationship_morph_one_or_many_sort_with_filter()
    {
        $query = QueryBuilder::fromInputs([
            'entity' => 'user',
            'sort' => [
                [
                    'property' => 'purchases.amount',
                    'aggregation' => 'count',
                    'filter' => [
                        'type' => 'relationship_condition',
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
            'order by COUNT("alias_purchases_1"."amount") ASC'
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
            'order by COUNT("alias_posts_1"."name") ASC'
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
            'order by COUNT("alias_sub_posts_1"."name") ASC'
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
            'order by SUM("alias_purchases_1"."amount") ASC'
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
            'order by SUM("alias_sub_purchases_1"."amount") ASC'
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
            'order by COUNT("alias_users_1"."id") ASC'
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
            'order by COUNT("alias_sub_users_1"."id") ASC'
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
            'order by AVG("alias_tags_1"."id") ASC'
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
            'order by AVG("alias_sub_tags_1"."id") ASC'
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
            'order by MIN("alias_posts_1"."name") ASC'
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
            'order by MIN("alias_sub_posts_1"."name") ASC'
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
            'order by COUNT("alias_posts_2"."name") ASC'
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
            'order by COUNT("alias_sub_posts_1"."name") ASC'
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
            'group by "users"."id" order by COUNT("alias_tags_4"."id") ASC'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
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
            'order by COUNT("alias_sub_tags_4"."id") ASC'
        );
        $this->assertEquals($rawSql, $query->toRawSql());

        // just verify that query works and doesn't throw exception
        $query->get();
    }
}
