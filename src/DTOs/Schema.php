<?php

namespace Comhon\EntityRequester\DTOs;

class Schema
{
    private array $data;

    public function __construct(array $data)
    {
        $indexArray = function (array $array) {
            $indexed = [];
            foreach ($array as $value) {
                $key = is_string($value) ? $value : $value['id'];
                $indexed[$key] = $value;
            }

            return $indexed;
        };

        $data['properties'] = $indexArray($data['properties'] ?? []);
        $data['scopes'] = $indexArray($data['scopes'] ?? []);

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

    public function getProperties(): array
    {
        return $this->data['properties'];
    }

    public function getProperty(string $propertyId): ?array
    {
        return $this->data['properties'][$propertyId] ?? null;
    }

    public function hasProperty(string $propertyId): bool
    {
        return isset($this->data['properties'][$propertyId]);
    }

    public function getScope(string $scopeId): ?array
    {
        return $this->data['scopes'][$scopeId] ?? null;
    }

    public function hasScope(string $scopeId): bool
    {
        return isset($this->data['scopes'][$scopeId]);
    }

    public function getDefaultSort(): ?array
    {
        return $this->data['default_sort'] ?? null;
    }
}
