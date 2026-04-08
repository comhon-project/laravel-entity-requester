# Lock Files

Lock files let you protect manually edited entries in your schemas from being overwritten when you regenerate with `entity-requester:make-model-schema`.

When the command regenerates a schema, it rebuilds every property and scope from the model. Any manual edit you made (changing a type, adding a custom property, tweaking scope parameters) would be lost. A lock file tells the command: "don't touch these entries".

Lock files are created manually by the developer. The command never writes or modifies them.

## Location

Lock files are placed alongside their corresponding schema files with a `.lock` extension:

```
schemas/
├── entities/
│   ├── user.json
│   └── user.lock      # Entity schema lock
└── requests/
    ├── user.json
    └── user.lock       # Request schema lock
```

## Entity Schema Lock

Controls which properties and scopes are preserved:

```json
{
  "properties": ["first_name", "posts", "custom_property"],
  "scopes": ["foo", "custom_scope"]
}
```

### Behavior

- If `first_name` exists in the existing entity schema, it stays unchanged — even if the model would generate something different
- If `first_name` doesn't exist in the existing schema (e.g., was manually removed), it stays absent — the lock only preserves, it never re-creates
- Properties/scopes **not** in the lock file are regenerated from the model

### Example

Your generated entity schema has `first_name` as `"type": "string"`, but you manually changed it to `"type": "float"`. Without a lock file, the next regeneration would overwrite it back to `string`. By adding `first_name` to the lock file, the command keeps your `float` definition untouched while regenerating everything else.

This also works for entries that don't come from the model at all (e.g., a `custom_property` you added by hand) — the lock prevents the command from removing it.

## Request Schema Lock

Controls which filtrable properties, scopes, and sortable fields are preserved:

```json
{
  "filtrable": {
    "properties": ["password", "name", "email"],
    "scopes": ["foo", "custom_scope"]
  },
  "sortable": ["password", "first_name", "name"]
}
```

### Behavior

Same rules as entity schema locks. This is useful when you've manually adjusted which properties are exposed through the API and don't want the command's `--filtrable` and `--sortable` options to override your choices.

## Behavior with `--fresh`

The `--fresh` option ignores lock files entirely and rebuilds schemas from scratch:

```bash
# Respects lock files (default)
php artisan entity-requester:make-model-schema User --filtrable=all --sortable=attributes

# Ignores lock files
php artisan entity-requester:make-model-schema User --filtrable=all --sortable=attributes --fresh
```
