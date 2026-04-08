# Validation Pipeline

The validation pipeline processes requests in three sequential steps: **Import**, **Authorize**, **Consistency Check**.

## Overview

```
Raw array input
       |
       v
  +-----------+
  |  Importer  |  Parse structure, create DTOs
  +-----------+
       |
       v
  +------------+
  | Authorizer |  Check request schema permissions
  +------------+
       |
       v
  +--------------------+
  | ConsistencyChecker |  Validate types, operators, relationships
  +--------------------+
       |
       v
  EntityRequest (validated)
```

## Usage

```php
use Comhon\EntityRequester\Facades\EntityRequestValidator;

// Entity resolved from the "entity" field in the request
$entityRequest = EntityRequestValidator::validate($request->all());

// Entity resolved from the provided model class
$entityRequest = EntityRequestValidator::validate($request->all(), User::class);
```

## Error Handling

All validation exceptions are renderable: Laravel automatically converts them into a `422` JSON response with a localized error message. There is no need to catch them manually.

Error messages are translated in 10 languages (en, fr, es, pt, ru, zh, ja, ar, hi, bn) using Laravel's localization system. Messages are automatically returned in the application's current locale (`app.locale`).

To customize messages, publish the language files:

```bash
php artisan vendor:publish --tag="entity-requester-lang"
```

This copies the translation files to `lang/vendor/entity-requester/` where you can edit them.

## Step 1: Import

The **Importer** parses the raw array into DTO objects:

- Validates the request structure (`entity`, `filter`, `sort` fields)
- Creates `Condition`, `Group`, `EntityCondition`, `MorphCondition`, and `Scope` DTOs
- Validates enum values and operator strings
- Resolves the model class from the entity name

## Step 2: Authorize

The **Authorizer** checks the request against the **request schema**:

- Every filtered property must be in `filtrable.properties`
- Every scope must be in `filtrable.scopes`
- Every sorted property must be in `sortable`
- Inline entity access is checked recursively

You can replace the built-in authorizer with your own implementation. Create a class that implements `EntityRequestAuthorizerInterface` and bind it in a service provider:

```php
use Comhon\EntityRequester\Interfaces\EntityRequestAuthorizerInterface;

$this->app->singleton(EntityRequestAuthorizerInterface::class, MyCustomAuthorizer::class);
```

## Step 3: Consistency Check

The **ConsistencyChecker** validates semantic correctness:

- Operator is valid for the property type
- Relationships exist in the entity schema
- Morph conditions target valid entities
- Scope parameters match the schema definition (count, types, nullable)
- To-many sort has an aggregation function
- Only one sort with a non-deterministic aggregation (`count`, `sum`, `avg`) is allowed per query
