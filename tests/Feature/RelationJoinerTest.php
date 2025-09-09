<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\Purchase;
use App\Models\User;
use Comhon\EntityRequester\Database\AliasCounter;
use Comhon\EntityRequester\Database\RelationJoiner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class RelationJoinerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AliasCounter::reset();
    }

    public function test_morph_to_relation_on_join_belongs_to_method()
    {
        $relation = (new Purchase)->buyer();
        $query = Post::query();

        $reflection = new ReflectionClass(RelationJoiner::class);
        $method = $reflection->getMethod('joinBelongsTo');
        $method->setAccessible(true);

        $this->expectExceptionMessage('$relation must not be instance of MorphTo');
        $method->invoke(null, $query, $relation);
    }

    public function test_joins_has_many_inner_join()
    {
        $relation = (new User)->posts();
        $query = User::query()->from('users');
        $alias = RelationJoiner::innerJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_posts_1', $alias);
        $this->assertEquals(
            $this->getRawSqlAccordingDriver('select * from "users" inner join "posts" as "alias_posts_1" on "users"."id" = "alias_posts_1"."owner_id"'),
            $sql
        );

        // just verify that query doesn't throw exception
        $query->get();
    }

    // ---------------- HasMany ----------------

    public function test_joins_has_many_with_alias()
    {
        $relation = (new User)->posts();
        $query = User::query()->from('users as u');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'u');
        $sql = $query->toRawSql();

        $this->assertEquals('alias_posts_1', $alias);
        $this->assertEquals(
            $this->getRawSqlAccordingDriver('select * from "users" as "u" left join "posts" as "alias_posts_1" on "u"."id" = "alias_posts_1"."owner_id"'),
            $sql
        );

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_has_many_without_alias()
    {
        $relation = (new User)->posts();
        $query = User::query()->from('users');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_posts_1', $alias);
        $this->assertEquals(
            $this->getRawSqlAccordingDriver('select * from "users" left join "posts" as "alias_posts_1" on "users"."id" = "alias_posts_1"."owner_id"'),
            $sql
        );

        // just verify that query doesn't throw exception
        $query->get();
    }

    // ---------------- BelongsTo ----------------

    public function test_joins_belongs_to_with_alias()
    {
        $relation = (new Post)->owner();
        $query = Post::query()->from('posts as p');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'p');
        $sql = $query->toRawSql();

        $this->assertEquals('alias_users_1', $alias);
        $this->assertEquals(
            $this->getRawSqlAccordingDriver('select * from "posts" as "p" left join "users" as "alias_users_1" on "p"."owner_id" = "alias_users_1"."id"'),
            $sql
        );

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_belongs_to_without_alias()
    {
        $relation = (new Post)->owner();
        $query = Post::query()->from('posts');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_users_1', $alias);
        $this->assertEquals(
            $this->getRawSqlAccordingDriver('select * from "posts" left join "users" as "alias_users_1" on "posts"."owner_id" = "alias_users_1"."id"'),
            $sql
        );

        // just verify that query doesn't throw exception
        $query->get();
    }

    // ---------------- BelongsToMany ----------------

    public function test_joins_belongs_to_many_with_alias()
    {
        $relation = (new User)->friends();
        $query = User::query()->from('users as u');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'u');
        $sql = $query->toRawSql();

        $this->assertEquals('alias_users_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "users" as "u" '
            .'left join "friendships" as "alias_friendships_2" on "u"."id" = "alias_friendships_2"."from_id" '
            .'inner join "users" as "alias_users_1" on "alias_users_1"."id" = "alias_friendships_2"."to_id"');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_belongs_to_many_without_alias()
    {
        $relation = (new User)->friends();
        $query = User::query()->from('users');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_users_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "users" '
            .'left join "friendships" as "alias_friendships_2" on "users"."id" = "alias_friendships_2"."from_id" '
            .'inner join "users" as "alias_users_1" on "alias_users_1"."id" = "alias_friendships_2"."to_id"');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    // ---------------- MorphMany ----------------

    public function test_joins_morph_many_with_alias()
    {
        $relation = (new User)->purchases();
        $query = User::query()->from('users as u');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'u');
        $sql = $query->toRawSql();

        $this->assertEquals('alias_purchases_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "users" as "u" '
            .'left join "purchases" as "alias_purchases_1" on "u"."id" = "alias_purchases_1"."buyer_id" and "alias_purchases_1"."buyer_type" = \'user\'');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_morph_many_without_alias()
    {
        $relation = (new User)->purchases();
        $query = User::query()->from('users');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_purchases_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "users" '
            .'left join "purchases" as "alias_purchases_1" on "users"."id" = "alias_purchases_1"."buyer_id" and "alias_purchases_1"."buyer_type" = \'user\'');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    // ---------------- MorphTo ----------------

    public function test_joins_morph_to_with_alias()
    {
        $relation = (new Purchase)->buyer();
        $query = Purchase::query()->from('purchases as p');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'p', User::class);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_users_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "purchases" as "p" '
            .'left join "users" as "alias_users_1" on "p"."buyer_id" = "alias_users_1"."id" and "p"."buyer_type" = \'user\'');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_morph_to_without_alias()
    {
        $relation = (new Purchase)->buyer();
        $query = Purchase::query()->from('purchases');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, null, User::class);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_users_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "purchases" '
            .'left join "users" as "alias_users_1" on "purchases"."buyer_id" = "alias_users_1"."id" and "purchases"."buyer_type" = \'user\'');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_morph_to_without_morph_class()
    {
        $relation = (new Purchase)->buyer();
        $query = Purchase::query();

        $this->expectExceptionMessage('$morphToTarget argument is required when using MorphTo relation');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
    }

    public function test_joins_morph_to_with_morph_class_doesnt_exists()
    {
        $relation = (new Purchase)->buyer();
        $query = Purchase::query();

        $this->expectExceptionMessage('$morphToTarget argument class not found');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, null, 'foo');
    }

    // ---------------- MorphToMany ----------------

    public function test_joins_morph_to_many_with_alias()
    {
        $relation = (new Post)->tags();
        $query = Post::query()->from('posts as p');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'p');
        $sql = $query->toRawSql();

        $this->assertEquals('alias_tags_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "posts" as "p" '
            .'left join "taggables" as "alias_taggables_2" on "p"."id" = "alias_taggables_2"."taggable_id" and "alias_taggables_2"."taggable_type" = \'post\' '
            .'inner join "tags" as "alias_tags_1" on "alias_tags_1"."id" = "alias_taggables_2"."tag_id"');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_morph_to_many_without_alias()
    {
        $relation = (new Post)->tags();
        $query = Post::query()->from('posts');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_tags_1', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "posts" '
            .'left join "taggables" as "alias_taggables_2" on "posts"."id" = "alias_taggables_2"."taggable_id" and "alias_taggables_2"."taggable_type" = \'post\' '
            .'inner join "tags" as "alias_tags_1" on "alias_tags_1"."id" = "alias_taggables_2"."tag_id"');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    // ---------------- HasManyThrough ----------------

    public function test_joins_has_many_through_with_alias()
    {
        $relation = (new User)->childrenPosts();
        $query = User::query()->from('users as u');
        $alias = RelationJoiner::leftJoinRelation($query, $relation, 'u');
        $sql = $query->toRawSql();

        $this->assertEquals('alias_posts_2', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "users" as "u" '
            .'left join "users" as "alias_users_1" on "u"."id" = "alias_users_1"."parent_id" '
            .'inner join "posts" as "alias_posts_2" on "alias_users_1"."id" = "alias_posts_2"."owner_id"');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }

    public function test_joins_has_many_through_without_alias()
    {
        $relation = (new User)->childrenPosts();
        $query = User::query()->from('users');
        $alias = RelationJoiner::leftJoinRelation($query, $relation);
        $sql = $query->toRawSql();

        $this->assertEquals('alias_posts_2', $alias);
        $expectedSql = $this->getRawSqlAccordingDriver('select * from "users" '
            .'left join "users" as "alias_users_1" on "users"."id" = "alias_users_1"."parent_id" '
            .'inner join "posts" as "alias_posts_2" on "alias_users_1"."id" = "alias_posts_2"."owner_id"');
        $this->assertEquals($expectedSql, $sql);

        // just verify that query doesn't throw exception
        $query->get();
    }
}
