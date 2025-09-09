<?php

namespace Comhon\EntityRequester\Database;

class Utils
{
    public static function generateAlias(string $table): string
    {
        return "alias_{$table}_".(AliasCounter::increment());
    }

    public static function qualify(string $column, ?string $table): string
    {
        if (! $table) {
            return $column;
        }

        $exploded = explode('.', $column);

        if (count($exploded) == 1) {
            array_unshift($exploded, $table);
        } else {
            $exploded[0] = $table;
        }

        return implode('.', $exploded);
    }
}
