<?php

namespace Comhon\EntityRequester\DTOs;

class Scope extends AbstractCondition
{
    public function __construct(private string $name, private ?array $parameters = null) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }
}
