# Conditions

A condition filters on a single property using an operator and a value.

## Structure

```json
{
  "type": "condition",
  "property": "email",
  "operator": "=",
  "value": "john@example.com"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | yes | Must be `"condition"` |
| `property` | `string` | yes | Property name from the entity schema |
| `operator` | `string` | yes | Comparison operator (see [Operators Reference](operators.md)) |
| `value` | `mixed` | yes | Value to compare against |

## Examples

```json
{ "type": "condition", "property": "status", "operator": "in", "value": [1, 2, 3] }
```

```json
{ "type": "condition", "property": "favorite_fruits", "operator": "contains", "value": "apple" }
```

```json
{ "type": "condition", "property": "favorite_fruits", "operator": "contains", "value": ["apple", "orange"] }
```

```json
{ "type": "condition", "property": "metadata", "operator": "has_key", "value": "address" }
```

Null values (for nullable properties):

```json
{ "type": "condition", "property": "birth_date", "operator": "=", "value": null }
```

Enum values (use the backed value, not the case name):

```json
{ "type": "condition", "property": "status", "operator": "=", "value": 1 }
```
