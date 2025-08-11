<?php

namespace Comhon\EntityRequester\Exceptions;

class EnumValueException extends RenderableException
{
    public function __construct(string $propertyName, string $enumClass)
    {
        $cases = $enumClass::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        parent::__construct("Invalid property '$propertyName', must be one of [".implode(', ', $values).']');
    }
}
