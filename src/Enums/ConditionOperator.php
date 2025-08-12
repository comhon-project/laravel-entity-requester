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
    case In = 'IN';
    case NotIn = 'NOT IN';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case Ilike = 'ILIKE';
    case NotIlike = 'NOT ILIKE';

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
