<?php

namespace Comhon\EntityRequester\Exceptions;

class EnumValueException extends InvalidEntityRequestException
{
    public function __construct(string $propertyName, string $enumClass)
    {
        $cases = $enumClass::cases();
        $values = implode(', ', array_map(fn ($case) => $case->value, $cases));

        parent::__construct('property_invalid_enum', ['property' => $propertyName, 'values' => $values]);
    }
}
