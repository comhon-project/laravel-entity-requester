# Morph Conditions

Morph conditions filter on polymorphic `MorphTo` relationships. They require specifying which entity types to check.

## Structure

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "buyer",
  "entities": ["user", "company"]
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | yes | Must be `"entity_condition"` |
| `operator` | `string` | yes | `"has"` or `"has_not"` |
| `property` | `string` | yes | MorphTo relationship name |
| `entities` | `array` | yes | List of entity names to check against |
| `filter` | `object` | no | Nested filter on the related entities |
| `count_operator` | `string` | no | Count comparison operator |
| `count` | `int` | no | Count value |

## Why `entities` Is Required

A `MorphTo` relationship can point to different model types. The package needs to know which types to query. The allowed values are defined in the entity schema's `entities` field on the morph property.

## Examples

### Basic Morph Check

Purchases where the buyer is a user or a company:

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "buyer",
  "entities": ["user", "company"]
}
```

### With Filter

Purchases where the buyer is a user named "john":

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "buyer",
  "entities": ["user"],
  "filter": {
    "type": "condition",
    "property": "first_name",
    "operator": "=",
    "value": "john"
  }
}
```

### With Count

Purchases where the buyer is a user, with count constraint:

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "buyer",
  "entities": ["user"],
  "count_operator": ">=",
  "count": 1
}
```

## Validation Rules

- The `property` must be a `morph_to` relationship in the entity schema
- Each value in `entities` must be listed in the entity schema's morph property `entities` array
- Using an entity condition on a `morph_to` without `entities` throws `MorphEntitiesRequiredException`
- Using an unknown entity throws `UnknownMorphEntityException`
