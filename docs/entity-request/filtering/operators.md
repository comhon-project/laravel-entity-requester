# Operators Reference

## Condition Operators

| Operator | Name | Value Type | Compatible Property Types | SQL Equivalent |
|----------|------|-----------|--------------------------|----------------|
| `=` | Equal | Scalar or `null` | All scalar types | `WHERE col = val` / `WHERE col IS NULL` |
| `<>` | Not Equal | Scalar or `null` | All scalar types | `WHERE col <> val` / `WHERE col IS NOT NULL` |
| `<` | Less Than | Scalar | All scalar types | `WHERE col < val` |
| `<=` | Less Than or Equal | Scalar | All scalar types | `WHERE col <= val` |
| `>` | Greater Than | Scalar | All scalar types | `WHERE col > val` |
| `>=` | Greater Than or Equal | Scalar | All scalar types | `WHERE col >= val` |
| `in` | In | Array of scalars | All scalar types | `WHERE col IN (...)` |
| `not_in` | Not In | Array of scalars | All scalar types | `WHERE col NOT IN (...)` |
| `like` | Like | String | All scalar types | `WHERE col LIKE val` |
| `not_like` | Not Like | String | All scalar types | `WHERE col NOT LIKE val` |
| `ilike` | Case-insensitive Like | String | All scalar types | `WHERE col ILIKE val` (PostgreSQL only) |
| `not_ilike` | Case-insensitive Not Like | String | All scalar types | `WHERE col NOT ILIKE val` (PostgreSQL only) |
| `contains` | Contains | Scalar or Array | `array` | `whereJsonContains(col, val)` |
| `not_contains` | Not Contains | Scalar or Array | `array` | `whereJsonDoesntContain(col, val)` |
| `has_key` | Has Key | String | `object` | `whereJsonContainsKey(col->key)` |
| `has_not_key` | Has Not Key | String | `object` | `whereJsonDoesntContainKey(col->key)` |

**Scalar types:** `string`, `integer`, `float`, `boolean`, `datetime`, `date`, `time`

## Operator-Type Compatibility

| Property Type | Allowed Operators |
|--------------|------------------|
| `string` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `integer` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `float` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `boolean` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `datetime` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `date` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `time` | `=`, `<>`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `like`, `not_like`, `ilike`, `not_ilike` |
| `array` | `contains`, `not_contains` |
| `object` | `has_key`, `has_not_key` |

Using an incompatible operator throws `InvalidOperatorForPropertyTypeException`.

## Entity Condition Operators

| Operator | Description | Eloquent Method |
|----------|------------|----------------|
| `has` | Relationship exists | `whereHas()` |
| `has_not` | Relationship doesn't exist | `whereDoesntHave()` |

## Count Operators (Math Operators)

Used with `count_operator` in entity conditions:

| Operator | Description |
|----------|------------|
| `=` | Equal to count |
| `<>` | Not equal to count |
| `<` | Less than count |
| `<=` | Less than or equal to count |
| `>` | Greater than count |
| `>=` | Greater than or equal to count |

## Aggregation Functions

Used in sorting on to-many relationships:

| Function | Description |
|----------|------------|
| `count` | Count of related records |
| `sum` | Sum of a related property |
| `avg` | Average of a related property |
| `min` | Minimum of a related property |
| `max` | Maximum of a related property |
