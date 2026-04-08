# Artisan Command

The `entity-requester:make-model-schema` command auto-generates entity, request, and enum schemas from Eloquent models.

## Usage

```bash
php artisan entity-requester:make-model-schema {model} [options]
```

### Arguments

| Argument | Description |
|----------|-------------|
| `model` | Model class name. Accepts fully qualified (`App\Models\User`) or just the class name (`User` — resolves to `App\Models\User` or `App\User`) |

### Options

| Option | Values | Description |
|--------|--------|-------------|
| `--filtrable` | `all`, `attributes`, `none`, `model` | Which properties are filtrable in the request schema |
| `--scopable` | `all`, `none`, `model` | Which scopes are filtrable |
| `--sortable` | `all`, `attributes`, `none`, `model` | Which properties are sortable |
| `--pretty` | (flag) | Format JSON with indentation |
| `--fresh` | (flag) | Ignore existing schemas and lock files, rebuild from scratch |

### Option Values Explained

| Value | `--filtrable` / `--sortable` | `--scopable` |
|-------|------------------------------|--------------|
| `all` | All properties including relationships | All scopes |
| `attributes` | Only scalar properties (no relationships) | -- |
| `none` | No properties | No scopes |
| `model` | Read from model's `$filtrable` / `$sortable` property | Read from model's `$scopable` property |

If an option is not provided, the command will prompt interactively.

## Examples

### Generate with all properties filtrable, attributes sortable

```bash
php artisan entity-requester:make-model-schema User \
  --filtrable=all \
  --scopable=all \
  --sortable=attributes \
  --pretty
```

### Generate from model-defined permissions

Define on the model:

```php
class User extends Model
{
    public array $filtrable = ['email', 'id', 'posts'];
    public array $sortable = ['id'];
    public array $scopable = ['foo', 'bool'];
}
```

Then:

```bash
php artisan entity-requester:make-model-schema User --filtrable=model --scopable=model --sortable=model
```

### Regenerate from scratch

```bash
php artisan entity-requester:make-model-schema User --filtrable=all --sortable=all --scopable=all --fresh
```

## Generated Files

The command creates/updates up to three types of files:

```
schemas/
├── entities/user.json    # Entity schema
├── requests/user.json    # Request schema
└── enums/status.json     # Enum schemas (one per detected enum)
```

## What It Detects

### Properties

- **Database columns**: reads column types from the database (supports MySQL, MariaDB, PostgreSQL, SQLite)
- **Cast types**: uses model `$casts` for more specific types (enums, arrays, objects, dates)
- **Relationships**: scans public methods that return `Relation` subclasses
- **Visibility**: respects `$hidden` and `$visible` model attributes
- **Deprecated methods**: methods with `#[Deprecated]` attribute are skipped

### Scopes

- Detects `scopeXxx` methods and named scopes
- Analyzes parameters: type, nullable, enum references
- Only includes scopes where all parameter types are resolvable

### Enums

- Auto-generates enum schemas for PHP backed enums found in casts or scope parameters
- Must be backed (`string` or `int`)

## Custom Type Resolvers

The command may not be able to resolve all types automatically (custom casts, non-standard column types, etc.). You can register custom resolvers in a service provider to handle these cases:

### Column Type Resolver

For database column types not handled by default:

```php
use Comhon\EntityRequester\Commands\MakeModelSchema;

MakeModelSchema::registerColumnTypeResolver(function (string $columnType): ?array {
    if ($columnType === 'money') {
        return ['type' => 'float'];
    }
    return null; // fallback to default
});
```

### Cast Type Resolver

For custom cast classes:

```php
MakeModelSchema::registerCastTypeResolver(function (string $castType): ?array {
    if ($castType === MyCast::class) {
        return ['type' => 'string'];
    }
    return null;
});
```

### Parameter Type Resolver

For scope parameter types not handled by default:

```php
MakeModelSchema::registerParamTypeResolver(function (\ReflectionParameter $param): ?array {
    if ($param->getType()?->getName() === MyCustomType::class) {
        return ['type' => 'string'];
    }
    return null;
});
```

All resolvers must return an array with at least a `type` key, or `null` to fallback to default resolution. The array can also include `enum` and `items` keys.

## Updating Existing Schemas

Without `--fresh`, the command merges changes into existing schemas:

- New properties/scopes are added
- Removed properties/scopes are removed
- [Lock files](schemas/lock-files.md) are respected: locked entries stay unchanged
