<?php

namespace Comhon\EntityRequester\Enums;

enum ConditionOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case In = 'in';
    case NotIn = 'not_in';
    case Like = 'like';
    case NotLike = 'not_like';
    case Ilike = 'ilike';
    case NotIlike = 'not_ilike';

    public function getSqlOperator(): string
    {
        return match ($this) {
            self::In, self::NotIn => throw new \LogicException("Operator {$this->value} must use whereIn/whereNotIn methods"),
            self::NotLike => 'not like',
            self::NotIlike => 'not ilike',
            default => $this->value,
        };
    }

    public function isSupported(): string
    {
        $isSupported = true;
        if ($this === self::Ilike || $this === self::NotIlike) {
            $connectionName = config('database.default');
            $driver = config("database.connections.{$connectionName}.driver");
            if ($driver !== 'pgsql') {
                $isSupported = false;
            }
        }

        return $isSupported;
    }

    public static function getSupportedOperators(): array
    {
        return array_filter(
            self::cases(),
            fn (self $case) => $case->isSupported(),
        );
    }
}
