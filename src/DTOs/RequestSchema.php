<?php

namespace Comhon\EntityRequester\DTOs;

class RequestSchema
{
    private array $data;

    /** @var array<string, RequestSchema> */
    private array $entities = [];

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

        $data['filtrable']['properties'] = $indexArray($data['filtrable']['properties'] ?? []);
        $data['filtrable']['scopes'] = $indexArray($data['filtrable']['scopes'] ?? []);
        $data['sortable'] = $indexArray($data['sortable'] ?? []);

        foreach ($data['entities'] ?? [] as $name => $entityData) {
            $this->entities[$name] = new RequestSchema($entityData);
        }
        unset($data['entities']);

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

    public function isFiltrable(string $propertyId): bool
    {
        return isset($this->data['filtrable']['properties'][$propertyId]);
    }

    public function isScopable(string $scopeId): bool
    {
        return isset($this->data['filtrable']['scopes'][$scopeId]);
    }

    public function isSortable(string $propertyId): bool
    {
        return isset($this->data['sortable'][$propertyId]);
    }

    /**
     * @return array<string, RequestSchema>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }
}
