# Installation

## Requirements

- PHP >= 8.2
- Laravel 11.x or 12.x

## Install

```bash
composer require comhon-project/laravel-entity-requester
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="entity-requester-config"
```

This publishes `config/entity-requester.php`.

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `use_cache` | `bool` | `false` | Enable schema caching (recommended in production) |
| `entity_schema_directory` | `?string` | `null` | Custom path for entity schemas. Default: `base_path('schemas/entities')` |
| `enum_schema_directory` | `?string` | `null` | Custom path for enum schemas. Default: `base_path('schemas/enums')` |
| `request_schema_directory` | `?string` | `null` | Custom path for request schemas. Default: `base_path('schemas/requests')` |

## Default Directory Structure

After generating schemas, your project will have:

```
your-project/
└── schemas/
    ├── entities/     # Entity schema JSON files
    ├── enums/        # Enum schema JSON files
    └── requests/     # Request schema JSON files
```
