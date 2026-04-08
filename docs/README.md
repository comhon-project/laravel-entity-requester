# Laravel Entity Requester

[![Latest Version on Packagist](https://img.shields.io/packagist/v/comhon-project/laravel-entity-requester.svg?style=flat-square)](https://packagist.org/packages/comhon-project/laravel-entity-requester)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/comhon-project/laravel-entity-requester/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/comhon-project/laravel-entity-requester/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Code Coverage](https://img.shields.io/codecov/c/github/comhon-project/laravel-entity-requester?label=coverage&style=flat-square)](https://codecov.io/gh/comhon-project/laravel-entity-requester)

A Laravel package that converts complex REST API filter/sort requests into Eloquent queries, with schema-based validation and authorization.

## Table of Contents

### Getting Started

- [Installation](installation.md) -- Install, configure, and publish the package
- [Quick Start](quick-start.md) -- From zero to a working query in minutes

### Architecture

- [Architecture](architecture.md) -- Pipeline and facades overview

### Schemas

- [Entity Schema](schemas/entity-schema.md) -- Define your model structure (properties, relationships, scopes)
- [Request Schema](schemas/request-schema.md) -- Control what can be filtered and sorted
- [Enum Schema](schemas/enum-schema.md) -- Define enum types for properties and scope parameters
- [Lock Files](schemas/lock-files.md) -- Preserve manual schema edits across regeneration
- [Caching](caching.md) -- Cache schemas for production

### Entity Request

#### Filtering

- [Conditions](entity-request/filtering/conditions.md) -- Filter on a single property
- [Groups](entity-request/filtering/groups.md) -- Combine filters with AND/OR logic
- [Entity Conditions](entity-request/filtering/entity-conditions.md) -- Filter on relationship existence (has/has_not)
- [Morph Conditions](entity-request/filtering/morph-conditions.md) -- Filter on polymorphic relationships
- [Scopes](entity-request/filtering/scopes.md) -- Use model scopes as filters
- [Operators Reference](entity-request/filtering/operators.md) -- Complete operator reference table

#### Sorting

- [Sorting](entity-request/sorting.md) -- Simple, relational, and aggregation-based sorting

### Usage

- [Validation Pipeline](usage/validation.md) -- Import, authorize, and consistency-check requests
- [Query Building](usage/query-building.md) -- Convert requests to Eloquent queries
- [Programmatic API](usage/programmatic-api.md) -- Build requests in PHP code

### Commands

- [Artisan Command](artisan-command.md) -- Auto-generate schemas from Eloquent models
