# Groups

Groups combine multiple filters with AND or OR logic.

## Structure

```json
{
  "type": "group",
  "operator": "and",
  "filters": []
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | yes | Must be `"group"` |
| `operator` | `string` | yes | `"and"` or `"or"` |
| `filters` | `array` | yes | Array of filter objects (conditions, groups, entity conditions, scopes) |

## AND Group

All conditions must match:

```json
{
  "type": "group",
  "operator": "and",
  "filters": [
    { "type": "condition", "property": "age", "operator": ">=", "value": 18 },
    { "type": "condition", "property": "email", "operator": "like", "value": "%@gmail.com" }
  ]
}
```

SQL equivalent: `WHERE age >= 18 AND email LIKE '%@gmail.com'`

## OR Group

At least one condition must match:

```json
{
  "type": "group",
  "operator": "or",
  "filters": [
    { "type": "condition", "property": "status", "operator": "=", "value": 1 },
    { "type": "condition", "property": "status", "operator": "=", "value": 2 }
  ]
}
```

SQL equivalent: `WHERE (status = 1 OR status = 2)`

## Nesting

Groups can be nested to build complex logic:

```json
{
  "type": "group",
  "operator": "and",
  "filters": [
    { "type": "condition", "property": "has_consumer_ability", "operator": "=", "value": true },
    {
      "type": "group",
      "operator": "or",
      "filters": [
        { "type": "condition", "property": "age", "operator": ">", "value": 21 },
        { "type": "condition", "property": "score", "operator": ">=", "value": 100 }
      ]
    }
  ]
}
```

SQL equivalent: `WHERE has_consumer_ability = true AND (age > 21 OR score >= 100)`

## Mixing Filter Types

Groups can contain any filter type:

```json
{
  "type": "group",
  "operator": "and",
  "filters": [
    { "type": "condition", "property": "email", "operator": "like", "value": "%@company.com" },
    { "type": "scope", "name": "validated" },
    { "type": "entity_condition", "operator": "has", "property": "posts" }
  ]
}
```
