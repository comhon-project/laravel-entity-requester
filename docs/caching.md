# Caching

Schema caching avoids reading and parsing JSON files on every request.

## Configuration

Enable caching by setting the `ENTITY_REQUESTER_CACHE` environment variable:

```env
ENTITY_REQUESTER_CACHE=true
```

When enabled, entity, request, and enum schemas are cached using Laravel's cache system.

## Refreshing the Cache

After modifying schema files, refresh the cache:

```php
use Comhon\EntityRequester\Facades\EntityRequester;

// Refresh a specific schema
EntityRequester::refreshEntityCache('user');
EntityRequester::refreshRequestCache('user');
EntityRequester::refreshEnumCache('status');
```

Flushing all cached schemas at once requires a cache driver that supports tags (e.g. Redis, Memcached):

```php
// Flush all schemas (entities + requests + enums)
EntityRequester::refreshCache();

// Flush all schemas of a given type
EntityRequester::refreshEntityCache();
EntityRequester::refreshRequestCache();
EntityRequester::refreshEnumCache();
```
