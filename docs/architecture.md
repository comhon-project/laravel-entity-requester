# Architecture

## Request Processing Pipeline

```
Client Request (JSON)
        |
        v
+------------------+
|    Importer       |  Parse raw array into DTOs
|                   |  (EntityRequest, Condition, Group,
|                   |   EntityCondition, MorphCondition, Scope)
+------------------+
        |
        v
+------------------+
|   Authorizer     |  Check against RequestSchema
|                   |  (filtrable, sortable, scopable)
+------------------+
        |
        v
+------------------+
| ConsistencyChecker|  Validate semantics
|                   |  (types, operators, relationships)
+------------------+
        |
        v
+------------------+
| EloquentBuilder  |  Convert to Eloquent query
|    Factory       |  (joins, subqueries, aggregations)
+------------------+
        |
        v
  Eloquent Builder
```

## Schema System

Two schemas work together to define and control API access:

```
EntitySchema                    RequestSchema
(what exists)                   (what's allowed)
+--------------------+          +--------------------+
| properties         |          | filtrable          |
|   - id, type       |          |   - properties[]   |
|   - nullable       |          |   - scopes[]       |
|   - enum, entity   |          | sortable[]         |
| scopes             |          | entities {}        |
|   - parameters     |          +--------------------+
| entities {}        |
| natural_sort       |
+--------------------+
```

- **EntitySchema** defines the model structure (what exists)
- **RequestSchema** defines access control (what's allowed via API)
- Together they enforce a strict contract: only declared and authorized properties can be queried

## Facades

| Facade | Resolves To |
|--------|------------|
| `EntityRequester` | Main service: configuration, cache management |
| `EntityRequestValidator` | Orchestrates the validation pipeline (import, authorize, consistency check) |
| `EloquentBuilderFactory` | Converts EntityRequest to Eloquent Builder |
