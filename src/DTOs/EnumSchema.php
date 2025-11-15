<?php

namespace Comhon\EntityRequester\DTOs;

class EnumSchema
{
    private array $data;

    public function __construct(array $data)
    {
        $indexed = [];
        foreach ($data['cases'] ?? [] as $case) {
            $indexed[$case['id']] = $case;
        }
        $data['cases'] = $indexed;

        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getId(): string
    {
        return $this->data['id'];
    }

    public function getType(): string
    {
        return $this->data['type'];
    }

    public function getCases(): array
    {
        return $this->data['cases'];
    }

    public function getCase(string|int $caseId): ?array
    {
        return $this->data['cases'][$caseId] ?? null;
    }

    public function hasCase(string|int $caseId): bool
    {
        return isset($this->data['cases'][$caseId]);
    }
}
