# Laravel Entity Requester

[![Latest Version on Packagist](https://img.shields.io/packagist/v/comhon-project/laravel-entity-requester.svg?style=flat-square)](https://packagist.org/packages/comhon-project/laravel-entity-requester)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/comhon-project/laravel-entity-requester/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/comhon-project/laravel-entity-requester/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Code Coverage](https://img.shields.io/codecov/c/github/comhon-project/laravel-entity-requester?label=coverage&style=flat-square)](https://codecov.io/gh/comhon-project/laravel-entity-requester)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/comhon-project/laravel-entity-requester/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/comhon-project/laravel-entity-requester/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/comhon-project/laravel-entity-requester.svg?style=flat-square)](https://packagist.org/packages/comhon-project/laravel-entity-requester)

A Laravel package that converts complex REST API filter/sort requests into Eloquent queries, with schema-based validation and authorization.

## Features

- **Schema-driven**: define entity structures and access control via JSON schemas
- **Complex filtering**: conditions, groups (AND/OR), relationship existence, morph relations, scopes
- **Sorting**: multi-column, through relationships, with aggregation functions
- **Validation & authorization**: requests are checked against entity and request schemas
- **Schema generation**: auto-generate schemas from Eloquent models
- **i18n**: validation messages translated in 10 languages

## Installation

```bash
composer require comhon-project/laravel-entity-requester
```

```bash
php artisan vendor:publish --tag="entity-requester-config"
```

## Quick start

Generate schemas from your model:

```bash
php artisan entity-requester:make-model-schema App\\Models\\User --filtrable=all --sortable=attributes
```

Build a query from a request:

```php
use Comhon\EntityRequester\Facades\EntityRequestValidator;
use Comhon\EntityRequester\Facades\EloquentBuilderFactory;

$entityRequest = EntityRequestValidator::validate($request->all());
$results = EloquentBuilderFactory::fromEntityRequest($entityRequest)->get();

// Or directly from inputs (skips authorization, use only with trusted sources)
$results = EloquentBuilderFactory::fromInputs($request->all())->get();
```

## Documentation

To learn more about Laravel Entity Requester, please see the [documentation](https://comhon-project.github.io/laravel-entity-requester/).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
