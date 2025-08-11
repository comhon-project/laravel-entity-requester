<?php

namespace Comhon\EntityRequester\Schema;

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
        $data['request']['filtrable']['properties'] = $indexArray($data['request']['filtrable']['properties'] ?? []);
        $data['request']['filtrable']['scopes'] = $indexArray($data['request']['filtrable']['scopes'] ?? []);
        $data['request']['sortable'] = $indexArray($data['request']['sortable'] ?? []);

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

    public function isFiltrable(string $propertyId): bool
    {
        return isset($this->data['request']['filtrable']['properties'][$propertyId]);
    }

    public function getScope(string $scopeId): ?array
    {
        return $this->data['request']['filtrable']['scopes'][$scopeId] ?? null;
    }

    public function isScopable(string $scopeId): bool
    {
        return isset($this->data['request']['filtrable']['scopes'][$scopeId]);
    }

    public function isSortable(string $propertyId): bool
    {
        return isset($this->data['request']['sortable'][$propertyId]);
    }

    public function getDefaultSort(): ?array
    {
        return $this->data['request']['default_sort'] ?? null;
    }
}
