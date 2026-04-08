# Query Building

The `EloquentBuilderFactory` converts requests into Eloquent query builders.

## From Validated Request

After validation, pass the `EntityRequest` to build the query:

```php
use Comhon\EntityRequester\Facades\EntityRequestValidator;
use Comhon\EntityRequester\Facades\EloquentBuilderFactory;

$entityRequest = EntityRequestValidator::validate($request->all());
$query = EloquentBuilderFactory::fromEntityRequest($entityRequest);

// Use the query
$results = $query->get();
$paginated = $query->paginate(15);
```

## From Raw Input (No Authorization)

Skip the authorization step and build directly from an array:

```php
use Comhon\EntityRequester\Facades\EloquentBuilderFactory;

// Entity resolved from the "entity" field
$query = EloquentBuilderFactory::fromInputs($request->all());

// Entity resolved from provided model class
$query = EloquentBuilderFactory::fromInputs($request->all(), User::class);
```

> **Warning:** `fromInputs` only runs the Import step (structure parsing). It skips authorization and consistency checks. Use only with trusted input.

## How Filters Map to Queries

| Filter Type                  | Eloquent Method                                                                                                     |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Condition `=`                | `where('col', '=', val)`                                                                                            |
| Condition `in`               | `whereIn('col', [...])`                                                                                             |
| Condition `like`             | `where('col', 'like', val)`                                                                                         |
| Condition `contains` (array) | `whereJsonContains('col', val)`                                                                                     |
| Condition `has_key` (object) | `whereJsonContainsKey('col->key')`                                                                                  |
| Group                        | `where(function ($q) { ... })` -- conditions inside use `where` (AND) or `orWhere` (OR) based on the group operator |
| EntityCondition `has`        | `whereHas('relation', ...)`                                                                                         |
| EntityCondition `has_not`    | `whereDoesntHave('relation', ...)`                                                                                  |
| MorphCondition `has`         | `whereHasMorph('relation', [...], ...)`                                                                             |
| Scope                        | `$query->scopeName(...params)`                                                                                      |

## How Sorting Works

| Sort Type                         | Approach                             |
| --------------------------------- | ------------------------------------ |
| Simple property                   | `orderBy('col', 'asc')`              |
| Through relationship              | Automatic join + `orderBy`           |
| To-many with aggregation          | `joinSub` + `groupBy` + `orderByRaw` |
| To-many with filter + aggregation | Filtered subquery join               |

> **Warning:** Sorts through relationships generate joins, and to-many sorts add `groupBy` with aggregation. Subqueries are used when the sort filter contains a scope or when the relation itself has conditions. These can produce complex queries that are slow on large datasets.
