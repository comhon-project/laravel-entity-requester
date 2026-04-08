# Request Schema

Request schemas define the authorization layer: which properties can be filtered, sorted, and which scopes can be used.

## Schema Directory

The default factory loads request schemas from JSON files in the `schemas/requests/` directory at the root of the project. This can be changed in `config/entity-requester.php`:

```php
'request_schema_directory' => base_path('custom/path/to/requests'),
```

This setting is only used by the default factory. If you use a custom factory (see below), it is responsible for loading schemas on its own.

## Custom Factory

You can replace the built-in factory with your own implementation (e.g., to load schemas from a database or an API). Create a class that implements `RequestSchemaFactoryInterface` and bind it in a service provider:

```php
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;

$this->app->singleton(RequestSchemaFactoryInterface::class, MyRequestSchemaFactory::class);
```

If your custom factory supports caching, implement `CacheableInterface` as well to enable cache refresh via `EntityRequester::refreshRequestCache()`.

## Structure

```json
{
  "id": "user",
  "filtrable": {
    "properties": [],
    "scopes": []
  },
  "sortable": [],
  "entities": {}
}
```

| Field | Type | Description |
|-------|------|-------------|
| `id` | `string` | Must match the entity schema `id` |
| `filtrable.properties` | `array` | Property ids allowed in filters |
| `filtrable.scopes` | `array` | Scope ids allowed in filters |
| `sortable` | `array` | Property ids allowed in sort |
| `entities` | `object` | Authorization rules for inline entities (JSON objects) |

## How It Works

When a request is validated with `EntityRequestValidator::validate()`, the **Authorizer** checks every property, scope, and sort field in the request against the request schema. If a property is not listed, the request is rejected with an appropriate exception.

> **Warning:** Be selective about which relationship properties you expose as sortable. Sorting through relationships generates joins and subqueries that can produce complex and slow queries on large datasets. See [Query Building](../usage/query-building.md#how-sorting-works) for details.

## Inline Entities

For JSON object properties that have inline entity definitions in the entity schema, you can control access to their sub-properties:

```json
{
  "id": "user",
  "filtrable": {
    "properties": ["id", "email", "metadata"],
    "scopes": ["validated"]
  },
  "sortable": ["id", "email", "metadata"],
  "entities": {
    "metadata": {
      "filtrable": {
        "properties": ["label", "address"]
      },
      "sortable": ["label", "address"]
    },
    "address": {
      "filtrable": {
        "properties": ["city", "zip"]
      },
      "sortable": ["city", "zip"]
    }
  }
}
```

In this example:
- `metadata` is filtrable/sortable at the top level
- Within `metadata`, only `label` and `address` are accessible
- Within `address`, only `city` and `zip` are accessible

## Complete Example

```json
{
  "id": "user",
  "filtrable": {
    "properties": [
      "id",
      "email",
      "first_name",
      "birth_date",
      "age",
      "score",
      "status",
      "favorite_fruits",
      "metadata",
      "has_consumer_ability",
      "email_verified_at",
      "posts",
      "friends",
      "purchases",
      "childrenPosts"
    ],
    "scopes": [
      "validated",
      "age",
      "bool",
      "carbon",
      "dateTime",
      "foo"
    ]
  },
  "sortable": [
    "id",
    "email",
    "birth_date",
    "age",
    "score",
    "status",
    "posts",
    "friends"
  ],
  "entities": {
    "metadata": {
      "filtrable": {
        "properties": ["label", "address"]
      },
      "sortable": ["label", "address"]
    },
    "address": {
      "filtrable": {
        "properties": ["city", "zip"]
      },
      "sortable": ["city", "zip"]
    }
  }
}
```
