# Quick Start

## 1. Generate Schemas from Your Model

```bash
php artisan entity-requester:make-model-schema User \
  --filtrable=all \
  --scopable=all \
  --sortable=attributes \
  --pretty
```

This generates three files:

- `schemas/entities/user.json` -- entity structure
- `schemas/requests/user.json` -- filtering/sorting permissions
- `schemas/enums/*.json` -- enum definitions (if any)

## 2. Use in a Controller

```php
use Comhon\EntityRequester\Facades\EntityRequestValidator;
use Comhon\EntityRequester\Facades\EloquentBuilderFactory;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Validate, authorize entity request inputs,
        // and build the entity request DTO
        $entityRequest = EntityRequestValidator::validate($request->all(), User::class);

        // Build the Eloquent query from the entity request
        $query = EloquentBuilderFactory::fromEntityRequest($entityRequest);

        return UserResource::collection($query->paginate());
    }
}
```

## 3. Send a Request

```http
GET /api/users
Content-Type: application/json

{
  "entity": "user",
  "filter": {
    "type": "group",
    "operator": "and",
    "filters": [
      {
        "type": "condition",
        "property": "email",
        "operator": "like",
        "value": "%@gmail.com"
      },
      {
        "type": "entity_condition",
        "operator": "has",
        "property": "posts",
        "count_operator": ">=",
        "count": 3
      }
    ]
  },
  "sort": [
    {
      "property": "email",
      "order": "asc"
    }
  ]
}
```

This returns all users with a Gmail address who have at least 3 posts, sorted by email.

## Shortcut: Skip Authorization

For trusted sources (internal jobs, admin commands), you can skip the authorization step:

```php
$users = EloquentBuilderFactory::fromInputs($request->all())->get();
```

> **Warning:** `fromInputs` skips the request schema authorization check. Only use this with trusted input.

## Saving Requests for Later

The entity request input is a plain array, so you can store it as JSON in the database and replay it later. This is useful for saved searches, user-defined filters, or scheduled reports:

```php
// Validate and persist the entity request in your own storage
$inputs = $request->only('entity', 'filter', 'sort');
$entityRequest = EntityRequestValidator::validate($inputs);

$savedFilter = SavedFilter::create([
    'name' => $request->input('name'),
    'payload' => $inputs,
]);

// Later, rebuild and execute the query from the saved payload
// No need to re-validate: the input was already validated before saving
$query = EloquentBuilderFactory::fromInputs($savedFilter->payload);
$results = $query->get();
```
