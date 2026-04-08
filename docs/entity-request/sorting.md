# Sorting

## Structure

Sort is an array of sort directives in the request:

```json
{
  "sort": [
    { "property": "email", "order": "asc" }
  ]
}
```

Each sort directive:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `property` | `string` | yes | Property path to sort by |
| `order` | `string` | no | `"asc"` (default) or `"desc"` |
| `aggregation` | `string` | no | Aggregation function for to-many relationships |
| `filter` | `object` | no | Filter applied before aggregation |

## Simple Sort

Sort by a direct model property:

```json
{
  "sort": [
    { "property": "email", "order": "asc" }
  ]
}
```

Multiple sort columns (applied in order):

```json
{
  "sort": [
    { "property": "name", "order": "asc" },
    { "property": "birth_date", "order": "desc" }
  ]
}
```

## Sort Through Relationships

Sort by a property on a related model. The package automatically joins the related table:

```json
{
  "sort": [
    { "property": "posts.name", "order": "asc" }
  ]
}
```

Deep traversal through multiple relations and objects:

```json
{
  "sort": [
    { "property": "posts.owner.metadata.address.city", "order": "asc" }
  ]
}
```

## Sort with Aggregation

For **to-many** relationships, you must specify an aggregation function. Without it, the query would be ambiguous (multiple related values for a single parent).

```json
{
  "sort": [
    { "property": "posts.name", "order": "desc", "aggregation": "max" }
  ]
}
```

### Available Aggregation Functions

| Function | Description |
|----------|------------|
| `count` | Number of related records |
| `sum` | Sum of the related property values |
| `avg` | Average of the related property values |
| `min` | Minimum value |
| `max` | Maximum value |

### Count Aggregation

`count` doesn't require a specific property, it counts the related records:

```json
{
  "sort": [
    { "property": "posts", "order": "desc", "aggregation": "count" }
  ]
}
```

Sort users by their number of posts (most posts first).

## Sort with Aggregation and Filter

Apply a filter before aggregating:

```json
{
  "sort": [
    {
      "property": "posts.name",
      "order": "desc",
      "aggregation": "max",
      "filter": {
        "type": "condition",
        "property": "name",
        "operator": "like",
        "value": "public%"
      }
    }
  ]
}
```

Sort users by the maximum post name among their posts that start with "public".

## Natural Sort

The entity schema can define a `natural_sort` field to describe the entity's natural ordering:

```json
{
  "natural_sort": [
    { "property": "name" },
    { "property": "first_name" }
  ]
}
```

This is a schema metadata field — it is **not** automatically applied as a default sort by the query builder.

## Constraints

- Sorting on a **to-many** relationship property without an `aggregation` throws `InvalidToManySortException`
- Only **one** non-deterministic aggregation sort (`count`, `sum`, `avg`) is allowed per query. Multiple non-deterministic sorts throw `MultipleUnsafeAggregationSortException`. Deterministic aggregations (`min`, `max`) have no limit.
- The property path must be traversable (all intermediate properties must be relationships or objects). Non-traversable properties throw `NonTraversablePropertyException`.
