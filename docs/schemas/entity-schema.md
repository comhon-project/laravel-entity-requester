# Entity Schema

Entity schemas define the structure of your models: properties, types, relationships, and scopes. They can be generated automatically with the [artisan command](../artisan-command.md).

## Schema Directory

The default factory loads entity schemas from JSON files in the `schemas/entities/` directory at the root of the project. This can be changed in `config/entity-requester.php`:

```php
'entity_schema_directory' => base_path('custom/path/to/entities'),
```

This setting is only used by the default factory. If you use a custom factory (see below), it is responsible for loading schemas on its own.

## Custom Factory

You can replace the built-in factory with your own implementation (e.g., to load schemas from a database or an API). Create a class that implements `EntitySchemaFactoryInterface` and bind it in a service provider:

```php
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;

$this->app->singleton(EntitySchemaFactoryInterface::class, MyEntitySchemaFactory::class);
```

If your custom factory supports caching, implement `CacheableInterface` as well to enable cache refresh via `EntityRequester::refreshEntityCache()`.

## Structure

```json
{
  "id": "user",
  "name": "user",
  "properties": [],
  "unique_identifier": "id",
  "primary_identifiers": ["name", "first_name"],
  "scopes": [],
  "natural_sort": [
    { "property": "name" },
    { "property": "first_name" }
  ],
  "entities": {}
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | `string` | yes | Unique identifier for the entity (used in requests and references) |
| `name` | `string` | yes | Human-readable name |
| `properties` | `array` | yes | List of property definitions |
| `unique_identifier` | `string` | no | Primary key column name |
| `primary_identifiers` | `array` | no | Columns that identify a record for display |
| `scopes` | `array` | no | Available model scopes |
| `natural_sort` | `array` | no | Describes the entity's natural ordering (schema metadata) |
| `entities` | `object` | no | Inline entity definitions (for object properties) |

## Properties

Each property is an object with the following fields:

```json
{
  "id": "email",
  "type": "string",
  "nullable": false
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | `string` | yes | Property name (matches column name or relation method) |
| `type` | `string` | yes | Data type (see below) |
| `nullable` | `bool` | yes (for non-relationship) | Whether the value can be null |
| `enum` | `string` | no | Reference to an enum schema id |
| `entity` | `string` | no | Reference to another entity (for object/relationship types) |
| `relationship_type` | `string` | no | Laravel relationship type (for relationship properties) |
| `items` | `object` | no | Item type definition (for array properties) |
| `morph_type` | `string` | no | Morph type column name (for morph_to) |
| `entities` | `array` | no | Allowed morph entity names (for morph_to) |
| `foreign_key` | `string` | no | Foreign key column name (for belongs_to) |

### Supported Types

| Type | Description |
|------|-------------|
| `string` | Text value |
| `integer` | Integer value |
| `float` | Decimal value |
| `boolean` | True/false |
| `datetime` | Date and time |
| `date` | Date only |
| `time` | Time only |
| `array` | JSON array |
| `object` | JSON object |
| `relationship` | Eloquent relation |

### Relationship Types

| Value | Laravel Class |
|-------|--------------|
| `has_many` | `HasMany` |
| `belongs_to` | `BelongsTo` |
| `belongs_to_many` | `BelongsToMany` |
| `has_many_through` | `HasManyThrough` |
| `morph_many` | `MorphMany` |
| `morph_to` | `MorphTo` |
| `morph_to_many` | `MorphToMany` |
| `has_one` | `HasOne` |
| `has_one_through` | `HasOneThrough` |
| `morph_one` | `MorphOne` |

### Examples

**Scalar property:**
```json
{ "id": "email", "type": "string", "nullable": false }
```

**Enum property:**
```json
{ "id": "status", "type": "integer", "enum": "status", "nullable": true }
```

**JSON array column with enum items:**
```json
{
  "id": "favorite_fruits",
  "type": "array",
  "items": { "type": "string", "enum": "fruit" },
  "nullable": true
}
```

**JSON object column:**
```json
{ "id": "metadata", "type": "object", "entity": "user.metadata", "nullable": true }
```

**HasMany relationship:**
```json
{ "id": "posts", "type": "relationship", "relationship_type": "has_many", "entity": "post" }
```

**BelongsTo relationship:**
```json
{
  "id": "parent",
  "type": "relationship",
  "relationship_type": "belongs_to",
  "entity": "user",
  "foreign_key": "parent_id"
}
```

**MorphTo relationship:**
```json
{
  "id": "buyer",
  "type": "relationship",
  "relationship_type": "morph_to",
  "morph_type": "buyer_type",
  "entities": ["user", "company"]
}
```

## Scopes

Define available model scopes with their parameters:

```json
{
  "scopes": [
    {
      "id": "validated",
      "parameters": []
    },
    {
      "id": "age",
      "parameters": [
        {
          "id": "age",
          "name": "age",
          "type": "integer",
          "nullable": false
        }
      ]
    },
    {
      "id": "foo",
      "parameters": [
        { "id": "foo", "name": "foo", "type": "string", "nullable": false },
        { "id": "bar", "name": "bar", "type": "float", "nullable": false },
        { "id": "fruit", "name": "fruit", "type": "string", "enum": "fruit", "nullable": false }
      ]
    }
  ]
}
```

Each parameter supports:

| Field | Type | Description |
|-------|------|-------------|
| `id` | `string` | Parameter identifier |
| `name` | `string` | Human-readable name |
| `type` | `string` | `string`, `integer`, `float`, `boolean`, `datetime`, `date`, `time` |
| `enum` | `string` | Optional reference to an enum schema |
| `nullable` | `bool` | Whether null is accepted |

## Inline Entities

Object properties reference an entity schema that describes the JSON structure. This entity schema can be defined inline in the `entities` section, or in a separate entity schema file:

```json
{
  "properties": [
    { "id": "metadata", "type": "object", "entity": "user.metadata", "nullable": true }
  ],
  "entities": {
    "metadata": {
      "properties": [
        { "id": "label", "type": "string", "nullable": true },
        { "id": "address", "type": "object", "entity": "user.address", "nullable": true }
      ]
    },
    "address": {
      "properties": [
        { "id": "city", "type": "string", "nullable": true },
        { "id": "zip", "type": "string", "nullable": true }
      ]
    }
  }
}
```

Inline entities are referenced by prefixing with the parent entity id: `user.metadata`, `user.address`. Inline entities cannot contain inline entities themselves — all inline entities are defined at the top-level `entities` section.

> **Note:** Inline entities are well suited for JSON columns whose structure is specific to the parent entity and not shared with other entities.

## Complete Example

```json
{
  "id": "user",
  "name": "user",
  "properties": [
    { "id": "id", "type": "integer", "nullable": false },
    { "id": "email", "type": "string", "nullable": false },
    { "id": "name", "type": "string", "nullable": false },
    { "id": "birth_date", "type": "datetime", "nullable": true },
    { "id": "score", "type": "float", "nullable": true },
    { "id": "status", "type": "integer", "enum": "status", "nullable": true },
    { "id": "favorite_fruits", "type": "array", "items": { "type": "string", "enum": "fruit" }, "nullable": true },
    { "id": "metadata", "type": "object", "entity": "user.metadata", "nullable": true },
    { "id": "has_consumer_ability", "type": "boolean", "nullable": false },
    { "id": "posts", "type": "relationship", "relationship_type": "has_many", "entity": "post" },
    { "id": "friends", "type": "relationship", "relationship_type": "belongs_to_many", "entity": "user" },
    { "id": "purchases", "type": "relationship", "relationship_type": "morph_many", "entity": "purchase" }
  ],
  "unique_identifier": "id",
  "primary_identifiers": ["name", "first_name"],
  "scopes": [
    { "id": "validated", "parameters": [] },
    { "id": "age", "parameters": [{ "id": "age", "name": "age", "type": "integer", "nullable": false }] }
  ],
  "natural_sort": [{ "property": "name" }, { "property": "first_name" }],
  "entities": {
    "metadata": {
      "properties": [
        { "id": "label", "type": "string", "nullable": true },
        { "id": "address", "type": "object", "entity": "user.address", "nullable": true }
      ]
    },
    "address": {
      "properties": [
        { "id": "city", "type": "string", "nullable": true },
        { "id": "zip", "type": "string", "nullable": true }
      ]
    }
  }
}
```
