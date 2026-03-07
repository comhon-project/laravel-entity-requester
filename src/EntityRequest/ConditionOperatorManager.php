<?php

namespace Comhon\EntityRequester\EntityRequest;

use Comhon\EntityRequester\Enums\ConditionOperator;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;

class ConditionOperatorManager implements ConditionOperatorManagerInterface
{
    private const OPERATORS_BY_PROPERTY_TYPE = [
        'array' => [ConditionOperator::Contains, ConditionOperator::NotContains],
        'default' => [
            ConditionOperator::Equal, ConditionOperator::NotEqual,
            ConditionOperator::LessThan, ConditionOperator::LessThanOrEqual,
            ConditionOperator::GreaterThan, ConditionOperator::GreaterThanOrEqual,
            ConditionOperator::In, ConditionOperator::NotIn,
            ConditionOperator::Like, ConditionOperator::NotLike,
            ConditionOperator::Ilike, ConditionOperator::NotIlike,
        ],
    ];

    public function getSqlOperator(ConditionOperator $operator): string
    {
        return match ($operator) {
            ConditionOperator::In, ConditionOperator::NotIn => throw new \LogicException("Operator {$operator->value} must use whereIn/whereNotIn methods"),
            ConditionOperator::Contains, ConditionOperator::NotContains => throw new \LogicException("Operator {$operator->value} must use whereJsonContains/whereJsonDoesntContain methods"),
            ConditionOperator::NotLike => 'not like',
            ConditionOperator::NotIlike => 'not ilike',
            default => $operator->value,
        };
    }

    public function isSupported(ConditionOperator $operator): bool
    {
        if ($operator === ConditionOperator::Ilike || $operator === ConditionOperator::NotIlike) {
            $connectionName = config('database.default');
            $driver = config("database.connections.{$connectionName}.driver");

            return $driver === 'pgsql';
        }

        return true;
    }

    public function getOperatorsForPropertyType(string $type): array
    {
        return self::OPERATORS_BY_PROPERTY_TYPE[$type] ?? self::OPERATORS_BY_PROPERTY_TYPE['default'];
    }
}
