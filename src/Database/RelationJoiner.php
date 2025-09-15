<?php

namespace Comhon\EntityRequester\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

class RelationJoiner
{
    public static function leftJoinRelation(
        Builder $query,
        Relation $relation,
        ?string $parentAlias = null,
        ?string $morphToTarget = null
    ): string {
        return self::joinRelation($query, $relation, 'left', $parentAlias, $morphToTarget);
    }

    public static function innerJoinRelation(
        Builder $query,
        Relation $relation,
        ?string $parentAlias = null,
        ?string $morphToTarget = null
    ): string {
        return self::joinRelation($query, $relation, 'inner', $parentAlias, $morphToTarget);
    }

    public static function joinRelation(
        Builder $query,
        Relation $relation,
        string $joinType = 'inner',
        ?string $parentAlias = null,
        ?string $morphToTarget = null,
    ): string {
        return match (true) {
            $relation instanceof HasOneOrMany => self::joinHasOneOrMany($query, $relation, $parentAlias, $joinType),
            $relation instanceof MorphTo => self::joinMorphTo($query, $relation, $morphToTarget, $parentAlias, $joinType),
            $relation instanceof BelongsTo => self::joinBelongsTo($query, $relation, $parentAlias, $joinType),
            $relation instanceof BelongsToMany => self::joinBelongsToMany($query, $relation, $parentAlias, $joinType),
            $relation instanceof HasOneOrManyThrough => self::joinHasOneOrManyThrough($query, $relation, $parentAlias, $joinType),
            default => throw new InvalidArgumentException('invalid relation type'),
        };
    }

    protected static function joinHasOneOrMany(
        Builder $query,
        HasOneOrMany $relation,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        if ($relation instanceof MorphOneOrMany) {
            return self::joinMorphOneOrMany($query, $relation, $parentAlias, $joinType);
        }

        $related = $relation->getRelated();

        $alias = Utils::generateAlias($related->getTable());
        $joinName = $related->getTable().' as '.$alias;

        $parentKey = Utils::qualify($relation->getQualifiedParentKeyName(), $parentAlias);
        $foreign = Utils::qualify($relation->getQualifiedForeignKeyName(), $alias);

        $query->join($joinName, $parentKey, '=', $foreign, $joinType);

        return $alias;
    }

    protected static function joinBelongsTo(
        Builder $query,
        BelongsTo $relation,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        if ($relation instanceof MorphTo) {
            throw new InvalidArgumentException('$relation must not be instance of MorphTo');
        }
        $related = $relation->getRelated();

        $alias = Utils::generateAlias($related->getTable());
        $joinName = $related->getTable().' as '.$alias;

        $foreign = Utils::qualify($relation->getQualifiedForeignKeyName(), $parentAlias);
        $ownerKey = Utils::qualify($relation->getQualifiedOwnerKeyName(), $alias);

        $query->join($joinName, $foreign, '=', $ownerKey, $joinType);

        return $alias;
    }

    protected static function joinBelongsToMany(
        Builder $query,
        BelongsToMany $relation,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        if ($relation instanceof MorphToMany) {
            return self::joinMorphToMany($query, $relation, $parentAlias, $joinType);
        }

        $parent = $relation->getParent();
        $related = $relation->getRelated();

        $relatedAlias = Utils::generateAlias($related->getTable());
        $pivotAlias = Utils::generateAlias($relation->getTable());

        $query->join(
            $relation->getTable().' as '.$pivotAlias,
            Utils::qualify($parent->qualifyColumn($parent->getKeyName()), $parentAlias),
            '=',
            $pivotAlias.'.'.$relation->getForeignPivotKeyName(),
            $joinType
        );

        $query->join(
            $related->getTable().' as '.$relatedAlias,
            $relatedAlias.'.'.$related->getKeyName(),
            '=',
            $pivotAlias.'.'.$relation->getRelatedPivotKeyName()
        );

        return $relatedAlias;
    }

    protected static function joinMorphOneOrMany(
        Builder $query,
        MorphOneOrMany $relation,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        $parent = $relation->getParent();
        $related = $relation->getRelated();

        $alias = Utils::generateAlias($related->getTable());
        $joinName = $related->getTable().' as '.$alias;

        $parentKey = Utils::qualify($parent->qualifyColumn($parent->getKeyName()), $parentAlias);
        $morphId = Utils::qualify($relation->getQualifiedForeignKeyName(), $alias);
        $morphType = Utils::qualify($relation->getQualifiedMorphType(), $alias);

        $query->join(
            $joinName,
            function ($join) use ($parentKey, $morphId, $morphType, $relation) {
                $join->on($parentKey, '=', $morphId)
                    ->where($morphType, '=', $relation->getMorphClass());
            },
            type: $joinType,
        );

        return $alias;
    }

    protected static function joinMorphToMany(
        Builder $query,
        MorphToMany $relation,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        $parent = $relation->getParent();
        $related = $relation->getRelated();

        $relatedAlias = Utils::generateAlias($related->getTable());
        $pivotAlias = Utils::generateAlias($relation->getTable());

        $parentKey = Utils::qualify($parent->qualifyColumn($parent->getKeyName()), $parentAlias);

        $query->join(
            $relation->getTable().' as '.$pivotAlias,
            function ($join) use ($parentKey, $pivotAlias, $relation) {
                $join->on($parentKey, '=', $pivotAlias.'.'.$relation->getForeignPivotKeyName())
                    ->where($pivotAlias.'.'.$relation->getMorphType(), '=', $relation->getMorphClass());
            },
            type: $joinType,
        );

        $query->join(
            $related->getTable().' as '.$relatedAlias,
            $relatedAlias.'.'.$relation->getRelatedKeyName(),
            '=',
            $pivotAlias.'.'.$relation->getRelatedPivotKeyName()
        );

        return $relatedAlias;
    }

    protected static function joinHasOneOrManyThrough(
        Builder $query,
        HasOneOrManyThrough $relation,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        $through = $relation->getParent();
        $related = $relation->getRelated();

        $throughTable = $through->getTable();
        $relatedTable = $related->getTable();

        $throughAlias = Utils::generateAlias($throughTable);
        $relatedAlias = Utils::generateAlias($relatedTable);

        $parentKey = Utils::qualify($relation->getQualifiedLocalKeyName(), $parentAlias);
        $throughForeignKey = Utils::qualify($relation->getQualifiedFirstKeyName(), $throughAlias);
        $throughLocalKey = Utils::qualify($relation->getQualifiedParentKeyName(), $throughAlias);
        $relatedForeignKey = Utils::qualify($relation->getQualifiedForeignKeyName(), $relatedAlias);

        $query->join(
            $throughTable.' as '.$throughAlias,
            $parentKey,
            '=',
            $throughForeignKey,
            $joinType
        );

        $query->join(
            $relatedTable.' as '.$relatedAlias,
            $throughLocalKey,
            '=',
            $relatedForeignKey
        );

        return $relatedAlias;
    }

    protected static function joinMorphTo(
        Builder $query,
        MorphTo $relation,
        ?string $morphToTarget,
        ?string $parentAlias = null,
        string $joinType = 'inner',
    ): string {
        if (! $morphToTarget) {
            throw new InvalidArgumentException('$morphToTarget argument is required when using MorphTo relation');
        }
        if (! class_exists($morphToTarget)) {
            throw new InvalidArgumentException('$morphToTarget argument class not found');
        }

        $target = new $morphToTarget;
        $parentTableName = $relation->getParent()->getTable();

        $alias = Utils::generateAlias($target->getTable());
        $joinName = $target->getTable().' as '.$alias;

        $morphId = Utils::qualify($relation->getQualifiedForeignKeyName(), $parentAlias);
        $morphType = Utils::qualify($parentTableName.'.'.$relation->getMorphType(), $parentAlias);
        $targetPk = $alias.'.'.$target->getKeyName();

        $query->join(
            $joinName,
            function ($join) use ($morphId, $morphType, $targetPk, $target, $morphToTarget) {
                $join->on($morphId, '=', $targetPk)
                    ->where($morphType, '=', $target->getMorphClass() ?? $morphToTarget);
            },
            type: $joinType,
        );

        return $alias;
    }

    public static function getjoinColumns(
        Relation $relation,
        ?string $aliasLeft = null,
        ?string $aliasRight = null
    ): array {
        if ($relation instanceof BelongsTo) {
            $leftOn = $relation->getQualifiedForeignKeyName();
            $rightOn = $relation->getQualifiedOwnerKeyName();
        } elseif ($relation instanceof HasOneOrMany) {
            $leftOn = $relation->getQualifiedParentKeyName();
            $rightOn = $relation->getQualifiedForeignKeyName();
        } elseif ($relation instanceof BelongsToMany) {
            $leftOn = $relation->getQualifiedParentKeyName();
            $rightOn = $relation->getQualifiedForeignPivotKeyName();
        } elseif ($relation instanceof HasOneOrManyThrough) {
            $leftOn = $relation->getQualifiedLocalKeyName();
            $rightOn = $relation->getQualifiedFirstKeyName();
        }

        return [
            Utils::qualify($leftOn, $aliasLeft),
            Utils::qualify($rightOn, $aliasRight),
        ];
    }
}
