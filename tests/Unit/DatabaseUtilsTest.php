<?php

namespace Tests\Unit;

use Comhon\EntityRequester\Database\Utils;
use PHPUnit\Framework\TestCase;

class DatabaseUtilsTest extends TestCase
{
    public function test_returns_the_same_value_if_alias_is_null()
    {
        $this->assertEquals('user.name', Utils::qualify('user.name', null));
    }

    public function test_returns_the_same_value_if_alias_is_empty_string()
    {
        $this->assertEquals('email', Utils::qualify('email', ''));
    }

    public function test_prefixes_the_alias_when_there_is_no_dot_in_the_string()
    {
        $this->assertEquals('u.email', Utils::qualify('email', 'u'));
    }

    public function test_replaces_the_first_segment_with_the_alias_when_there_is_a_dot()
    {
        $this->assertEquals('alias.column', Utils::qualify('table.column', 'alias'));
    }
}
