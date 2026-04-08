# Programmatic API

Build requests in PHP code instead of from JSON input.

## EntityRequest

```php
use Comhon\EntityRequester\DTOs\EntityRequest;
use App\Models\User;

$entityRequest = new EntityRequest(User::class);
```

## Adding Conditions

```php
use Comhon\EntityRequester\DTOs\Condition;
use Comhon\EntityRequester\Enums\ConditionOperator;

$condition = new Condition('email', ConditionOperator::Equal, 'john@example.com');
$entityRequest->addFilter($condition);
```

`addFilter` accepts a single condition or an array. The second parameter controls the logic:

```php
// AND (default)
$entityRequest->addFilter($condition, and: true);

// OR
$entityRequest->addFilter($condition, and: false);
```

When adding multiple filters, `addFilter` wraps them in a Group automatically:

```php
$entityRequest->addFilter([
    new Condition('age', ConditionOperator::GreaterThanOrEqual, 18),
    new Condition('email', ConditionOperator::Like, '%@gmail.com'),
]);
```

## Setting a Complex Filter

For full control, use `setFilter`:

```php
use Comhon\EntityRequester\DTOs\Group;
use Comhon\EntityRequester\Enums\GroupOperator;

$group = new Group(GroupOperator::And);
$group->add(new Condition('age', ConditionOperator::GreaterThanOrEqual, 18));
$group->add(new Condition('email', ConditionOperator::Like, '%@gmail.com'));

$entityRequest->setFilter($group);
```

## Entity Conditions

```php
use Comhon\EntityRequester\DTOs\EntityCondition;
use Comhon\EntityRequester\Enums\EntityConditionOperator;
use Comhon\EntityRequester\Enums\MathOperator;

// Basic existence
$hasPost = new EntityCondition('posts', EntityConditionOperator::Has);

// With nested filter and count
$hasManyPosts = new EntityCondition(
    property: 'posts',
    operator: EntityConditionOperator::Has,
    filter: new Condition('name', ConditionOperator::Equal, 'public'),
    countOperator: MathOperator::GreaterThanOrEqual,
    count: 5,
);

$entityRequest->addFilter($hasManyPosts);
```

## Morph Conditions

```php
use Comhon\EntityRequester\DTOs\MorphCondition;

$morph = new MorphCondition(
    property: 'buyer',
    operator: EntityConditionOperator::Has,
    entities: ['user'],
    filter: new Condition('first_name', ConditionOperator::Equal, 'john'),
);

$entityRequest->addFilter($morph);
```

## Scopes

```php
use Comhon\EntityRequester\DTOs\Scope;

// Without parameters
$entityRequest->addFilter(new Scope('validated'));

// With parameters
$entityRequest->addFilter(new Scope('foo', ['hello', 123.45, 'apple']));
```

## Sorting

```php
use Comhon\EntityRequester\Enums\OrderDirection;
use Comhon\EntityRequester\Enums\AggregationFunction;

// Simple sort
$entityRequest->addSort('email', OrderDirection::Asc);

// Sort with aggregation
$entityRequest->addSort(
    property: 'posts.name',
    order: OrderDirection::Desc,
    aggregation: AggregationFunction::Max,
);

// Sort with aggregation and filter
$entityRequest->addSort(
    property: 'posts.name',
    order: OrderDirection::Desc,
    filter: new Condition('name', ConditionOperator::Like, 'public%'),
    aggregation: AggregationFunction::Max,
);
```

## Building the Query

```php
use Comhon\EntityRequester\Facades\EloquentBuilderFactory;

$query = EloquentBuilderFactory::fromEntityRequest($entityRequest);
$results = $query->get();
```
