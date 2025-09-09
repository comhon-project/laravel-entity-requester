<?php

namespace Comhon\EntityRequester\Database;

class AliasCounter
{
    private static int $current = 0;

    public static function increment()
    {
        return ++self::$current;
    }

    public static function reset()
    {
        return self::$current = 0;
    }
}
