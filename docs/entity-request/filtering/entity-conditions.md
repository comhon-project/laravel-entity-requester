# Entity Conditions

Entity conditions filter based on relationship existence and JSON column traversal. For relationships, they translate to `whereHas` / `whereDoesntHave` Eloquent methods.

## Structure

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "posts"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | yes | Must be `"entity_condition"` |
| `operator` | `string` | yes | `"has"` or `"has_not"` |
| `property` | `string` | yes | Relationship property name |
| `filter` | `object` | no | Nested filter applied to the related entity |
| `count_operator` | `string` | no | Count comparison: `=`, `<>`, `<`, `<=`, `>`, `>=` |
| `count` | `int` | no | Count value (must be > 0) |

## Basic Existence

Has at least one related record:

```json
{ "type": "entity_condition", "operator": "has", "property": "posts" }
```

Has no related records:

```json
{ "type": "entity_condition", "operator": "has_not", "property": "friends" }
```

## With Count

Filter by number of related records:

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "posts",
  "count_operator": ">=",
  "count": 5
}
```

SQL equivalent: users that have at least 5 posts.

## With Nested Filter

Apply a filter on the related entity. The `filter` field accepts any filter type (condition, group, entity condition, morph condition, scope):

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "posts",
  "filter": {
    "type": "condition",
    "property": "name",
    "operator": "=",
    "value": "public"
  }
}
```

SQL equivalent: users that have posts where `name = 'public'`.

## With Count and Filter Combined

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "posts",
  "filter": {
    "type": "condition",
    "property": "name",
    "operator": "like",
    "value": "%draft%"
  },
  "count_operator": ">=",
  "count": 3
}
```

Users with at least 3 posts matching the filter.

## JSON Columns

Entity conditions can filter inside JSON columns. Properties with `object` type reference an entity schema that describes the JSON structure, allowing you to traverse nested values:

```json
{
  "type": "entity_condition",
  "operator": "has",
  "property": "metadata",
  "filter": {
    "type": "entity_condition",
    "operator": "has",
    "property": "address",
    "filter": {
      "type": "condition",
      "property": "city",
      "operator": "=",
      "value": "Paris"
    }
  }
}
```

This traverses `metadata.address.city` in the JSON column.

> **Note:** On non-relationship (object) properties, `has_not` with a filter, `count_operator`/`count`, morph conditions, and scopes are not supported.

## Supported Relationship Types

Entity conditions work with all relationship types:
- `HasMany`, `HasOne`
- `BelongsTo`, `BelongsToMany`
- `HasManyThrough`, `HasOneThrough`
- `MorphMany`, `MorphOne`, `MorphToMany`
- `MorphTo` (requires [morph conditions](morph-conditions.md))
