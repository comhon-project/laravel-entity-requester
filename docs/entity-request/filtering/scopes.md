# Scopes

Scopes allow using Laravel model scopes as filters in requests.

## Structure

```json
{
  "type": "scope",
  "name": "validated"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | yes | Must be `"scope"` |
| `name` | `string` | yes | Scope name (as defined in entity schema) |
| `parameters` | `array` | no | Ordered list of parameter values |

## Without Parameters

```json
{ "type": "scope", "name": "validated" }
```

Calls `$query->validated()` on the model.

## With Parameters

```json
{
  "type": "scope",
  "name": "foo",
  "parameters": ["hello", 123.45, "apple"]
}
```

Calls `$query->foo("hello", 123.45, "apple")`.

Parameters are validated against the scope definition in the entity schema.

## Parameter Types

Supported parameter types and their expected JSON values:

| Type | JSON Value | PHP Type After Cast |
|------|-----------|-------------------|
| `string` | `"text"` | `string` |
| `integer` | `42` | `int` |
| `float` | `3.14` | `float` |
| `boolean` | `true` / `false` | `bool` |
| `datetime` | `"2024-01-15 10:30:00"` | `Carbon` |
| `date` | `"2024-01-15"` | `Carbon` |
| `time` | `"10:30:00"` | `Carbon` |

### Enum Parameters

If a parameter has an `enum` reference in the schema, the value is validated against the enum's cases and automatically cast to the PHP enum:

```json
{
  "type": "scope",
  "name": "foo",
  "parameters": ["hello", 3.14, "apple"]
}
```

Where `apple` is validated against the `fruit` enum and cast to `Fruit::Apple`.

### Nullable Parameters

If a parameter is `nullable: true` in the schema, `null` is accepted:

```json
{
  "type": "scope",
  "name": "carbon",
  "parameters": [null]
}
```

## In Groups

Scopes can be combined with other filter types in groups:

```json
{
  "type": "group",
  "operator": "and",
  "filters": [
    { "type": "scope", "name": "validated" },
    { "type": "condition", "property": "age", "operator": ">=", "value": 18 }
  ]
}
```

## Restrictions

- Scopes are not supported inside non-relationship entity conditions (i.e., inside object-type entity conditions)
- Scope parameters must match the number and types defined in the entity schema
- The scope must be listed in the request schema's `filtrable.scopes` for authorized requests
