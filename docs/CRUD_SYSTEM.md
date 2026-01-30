# CRUD System

## Overview

The CRUD system provides a **dynamic, entity-agnostic** API for Create, Read, Update, and Delete operations on any Eloquent model. It supports advanced features like filtering, sorting, pagination, relations, computed columns, and integrates with the ACL system for row-level security.

## Key Features

- **Dynamic Entity Resolution**: Works with any Eloquent model via `DynamicEntity`
- **Advanced Filtering**: Nested filters with AND/OR logic
- **Relation Support**: Eager loading with column selection
- **Pagination**: Multiple pagination strategies (page-based, from-to, limit)
- **Computed Columns**: Support for appends and method calls
- **ACL Integration**: Automatic row-level security filtering
- **Caching**: Built-in response caching
- **Versioning**: History tracking for models with `Versionable` trait
- **Approval Workflow**: Support for modification approval

## Architecture

### Service Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    CrudController                                │
│  Routes HTTP requests to CrudService                            │
│  ├── list()    → ListRequest    → ListRequestData               │
│  ├── detail()  → DetailRequest  → DetailRequestData             │
│  ├── insert()  → ModifyRequest  → ModifyRequestData             │
│  ├── update()  → ModifyRequest  → ModifyRequestData             │
│  ├── delete()  → ModifyRequest  → ModifyRequestData             │
│  ├── history() → HistoryRequest → HistoryRequestData            │
│  └── tree()    → TreeRequest    → TreeRequestData               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    CrudService                                   │
│  Orchestrates CRUD operations                                   │
│  ├── Uses AuthorizationService for permissions + ACL            │
│  ├── Uses QueryBuilder for query preparation                    │
│  └── Returns CrudResult with data + metadata                    │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐    ┌─────────────────────────┐
│  AuthorizationService   │    │      QueryBuilder       │
│  ├── ensurePermission() │    │  ├── prepareQuery()     │
│  ├── injectAclFilters() │    │  └── applyFilters()     │
│  └── applyAclFilters..  │    └─────────────────────────┘
└─────────────────────────┘
```

### Request Flow

```
HTTP Request
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  1. FormRequest (ListRequest, DetailRequest, etc.)              │
│     - Validates input                                           │
│     - Calls parsed() to create RequestData                      │
└─────────────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  2. RequestData (ListRequestData, DetailRequestData, etc.)      │
│     - Resolves entity via DynamicEntity                         │
│     - Normalizes columns, filters, sorts, relations             │
└─────────────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  3. CrudService                                                 │
│     - Checks permission (AuthorizationService)                  │
│     - Injects ACL filters into RequestData                      │
│     - Builds query (QueryBuilder)                               │
│     - Executes query                                            │
│     - Returns CrudResult                                        │
└─────────────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│  4. ResponseBuilder                                             │
│     - Formats response (JSON/XML)                               │
│     - Applies caching                                           │
│     - Returns HTTP Response                                     │
└─────────────────────────────────────────────────────────────────┘
```

## API Endpoints

### Read Operations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/select/{entity}` | GET, POST | List records with filtering/pagination |
| `/api/v1/detail/{entity}` | GET | Get single record by primary key |
| `/api/v1/history/{entity}` | GET | Get record with version history |
| `/api/v1/tree/{entity}` | GET | Get hierarchical record (ancestors/descendants) |

### Write Operations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/insert/{entity}` | POST | Create new record |
| `/api/v1/update/{entity}` | PATCH | Update existing record(s) |
| `/api/v1/delete/{entity}` | DELETE | Hard delete record(s) |
| `/api/v1/activate/{entity}` | POST | Restore soft-deleted record |
| `/api/v1/inactivate/{entity}` | POST | Soft delete record |

### Special Operations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/approve/{entity}` | POST | Approve pending modification |
| `/api/v1/disapprove/{entity}` | POST | Reject pending modification |
| `/api/v1/lock/{entity}` | POST | Lock record for editing |
| `/api/v1/unlock/{entity}` | POST | Unlock record |

## Request Parameters

### List Request

```json
{
  "connection": "default",
  "columns": ["id", "name", "created_at"],
  "relations": ["author", "categories"],
  "filters": {
    "operator": "and",
    "filters": [
      { "property": "status", "operator": "=", "value": "published" },
      { "property": "created_at", "operator": ">=", "value": "2024-01-01" }
    ]
  },
  "sort": [
    { "property": "created_at", "direction": "desc" }
  ],
  "pagination": 25,
  "page": 1
}
```

### Detail Request

```json
{
  "connection": "default",
  "id": 123,
  "columns": ["id", "name", "content", "author.name"],
  "relations": ["author", "categories", "comments"]
}
```

### Modify Request (Insert/Update)

```json
{
  "connection": "default",
  "id": 123,
  "changes": {
    "name": "Updated Title",
    "status": "published"
  }
}
```

## Column Types

Columns can be of different types:

| Type | Description | Example |
|------|-------------|---------|
| `column` | Standard database column | `name`, `created_at` |
| `append` | Eloquent accessor (appended attribute) | `full_name` |
| `method` | Model method call | `getFormattedDate()` |
| `count` | Aggregate count | `comments:count` |
| `sum` | Aggregate sum | `items:sum:quantity` |
| `avg` | Aggregate average | `reviews:avg:rating` |
| `min` | Aggregate minimum | `prices:min:amount` |
| `max` | Aggregate maximum | `prices:max:amount` |

### Column Syntax

```json
{
  "columns": [
    "id",
    "name",
    { "name": "full_name", "type": "append" },
    { "name": "author.name", "type": "column" },
    { "name": "comments", "type": "count" }
  ]
}
```

## Filters

### Filter Operators

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `=` | Equals | `"active"` |
| `!=` | Not equals | `"deleted"` |
| `>` | Greater than | `100` |
| `>=` | Greater than or equal | `100` |
| `<` | Less than | `100` |
| `<=` | Less than or equal | `100` |
| `like` | LIKE pattern | `"%john%"` |
| `not like` | NOT LIKE pattern | `"%test%"` |
| `in` | IN list | `["active", "pending"]` |
| `between` | BETWEEN range | `["2024-01-01", "2024-12-31"]` |

### Nested Filters

Filters can be nested with AND/OR logic:

```json
{
  "operator": "and",
  "filters": [
    { "property": "status", "operator": "=", "value": "active" },
    {
      "operator": "or",
      "filters": [
        { "property": "priority", "operator": "=", "value": "high" },
        { "property": "urgent", "operator": "=", "value": true }
      ]
    }
  ]
}
```

Result: `status = 'active' AND (priority = 'high' OR urgent = true)`

### Relation Filters

Filter on related models using dot notation:

```json
{
  "filters": [
    { "property": "author.country", "operator": "=", "value": "IT" },
    { "property": "categories.slug", "operator": "in", "value": ["news", "blog"] }
  ]
}
```

## Pagination

### Page-Based Pagination

```json
{
  "pagination": 25,
  "page": 2
}
```

Response includes:
- `meta.totalRecords` - Total count
- `meta.currentRecords` - Records in current page
- `meta.currentPage` - Current page number
- `meta.totalPages` - Total pages
- `meta.pagination` - Items per page

### From-To Pagination

```json
{
  "from": 50,
  "to": 100
}
```

### Limit-Based

```json
{
  "limit": 10
}
```

## Relations

### Simple Relation

```json
{
  "relations": ["author", "categories"]
}
```

### Nested Relations

```json
{
  "relations": ["author.profile", "categories.parent"]
}
```

### Relation with Column Selection

Specify columns for relations using dot notation:

```json
{
  "columns": [
    "id",
    "title",
    "author.id",
    "author.name",
    "categories.id",
    "categories.name"
  ],
  "relations": ["author", "categories"]
}
```

## Response Format

### Success Response

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Article 1", "author": { "id": 5, "name": "John" } },
    { "id": 2, "name": "Article 2", "author": { "id": 3, "name": "Jane" } }
  ],
  "meta": {
    "totalRecords": 150,
    "currentRecords": 25,
    "currentPage": 1,
    "totalPages": 6,
    "pagination": 25,
    "from": 1,
    "to": 25,
    "class": "App\\Models\\Article",
    "table": "articles",
    "cachedAt": "2024-01-15T10:30:00Z"
  }
}
```

### Error Response

```json
{
  "success": false,
  "error": "User not allowed to access this resource",
  "statusCode": 403
}
```

## Dynamic Entity Resolution

The CRUD system uses `DynamicEntity` to resolve model classes from entity names:

```php
// Entity name → Model class
"users"     → App\Models\User
"articles"  → Modules\Cms\Models\Article
"orders"    → Modules\Shop\Models\Order
```

Resolution is based on:
1. Registered entity mappings
2. Table name matching
3. Class name convention

## ACL Integration

For read operations, ACL filters are automatically injected:

```
User Request: { filters: [status = 'active'] }
ACL Filters:  { filters: [department_id = 5] }

Combined:     { filters: [department_id = 5] AND [status = 'active'] }
```

This ensures users can only see records they're authorized to access, regardless of what filters they specify.

See [ACL_SYSTEM.md](./ACL_SYSTEM.md) for details.

## Computed Columns

Models can define dependencies for computed columns:

```php
class Article extends Model
{
    protected $appends = ['full_title'];
    
    public function getFullTitleAttribute(): string
    {
        return $this->title . ' by ' . $this->author->name;
    }
    
    public function crudComputedDependencies(): array
    {
        return [
            'full_title' => [
                'columns' => ['title'],
                'relations' => ['author'],
            ],
        ];
    }
}
```

This allows QueryBuilder to optimize column selection and eager loading.

## Model Features

### Versionable (History)

Models using `Versionable` trait support history tracking:

```php
class Article extends Model
{
    use Versionable;
    
    protected $versionable = ['title', 'content', 'status'];
}
```

Access via `/api/v1/history/{entity}`.

### HasRecursiveRelationships (Tree)

Models using `HasRecursiveRelationships` support hierarchical queries:

```php
class Category extends Model
{
    use HasRecursiveRelationships;
}
```

Access via `/api/v1/tree/{entity}` with `parents` and/or `children` parameters.

### HasLocks (Locking)

Models using `HasLocks` trait support record locking:

```php
class Article extends Model
{
    use HasLocks;
}
```

Lock/unlock via `/api/v1/lock/{entity}` and `/api/v1/unlock/{entity}`.

### RequiresApproval (Approval Workflow)

Models using `RequiresApproval` trait support modification approval:

```php
class Article extends Model
{
    use RequiresApproval;
    
    protected function requiresApprovalWhen(array $modifications): bool
    {
        return isset($modifications['status']);
    }
}
```

Approve/reject via `/api/v1/approve/{entity}` and `/api/v1/disapprove/{entity}`.

## File Structure

### Controllers

| File | Purpose |
|------|---------|
| `Http/Controllers/CrudController.php` | Main CRUD controller |

### Services

| File | Purpose |
|------|---------|
| `Services/Crud/CrudService.php` | CRUD business logic orchestrator |
| `Services/Crud/QueryBuilder.php` | Eloquent query preparation |
| `Services/Authorization/AuthorizationService.php` | Permissions + ACL |

### Request Data

| File | Purpose |
|------|---------|
| `Casts/CrudRequestData.php` | Base request data class |
| `Casts/SelectRequestData.php` | Read operations base |
| `Casts/ListRequestData.php` | List with filters/pagination |
| `Casts/DetailRequestData.php` | Single record detail |
| `Casts/HistoryRequestData.php` | Record with history |
| `Casts/TreeRequestData.php` | Hierarchical record |
| `Casts/ModifyRequestData.php` | Insert/Update/Delete |

### DTOs

| File | Purpose |
|------|---------|
| `Services/Crud/DTOs/CrudResult.php` | Operation result wrapper |
| `Services/Crud/DTOs/CrudMeta.php` | Result metadata |

### Form Requests

| File | Purpose |
|------|---------|
| `Http/Requests/CrudRequest.php` | Base validation |
| `Http/Requests/ListRequest.php` | List validation |
| `Http/Requests/DetailRequest.php` | Detail validation |
| `Http/Requests/ModifyRequest.php` | Modify validation |
| `Http/Requests/HistoryRequest.php` | History validation |
| `Http/Requests/TreeRequest.php` | Tree validation |

## Usage Examples

### List with Filters

```bash
curl -X POST /api/v1/select/articles \
  -H "Content-Type: application/json" \
  -d '{
    "columns": ["id", "title", "author.name", "created_at"],
    "relations": ["author"],
    "filters": {
      "operator": "and",
      "filters": [
        { "property": "status", "operator": "=", "value": "published" }
      ]
    },
    "sort": [{ "property": "created_at", "direction": "desc" }],
    "pagination": 10,
    "page": 1
  }'
```

### Get Single Record

```bash
curl /api/v1/detail/articles?id=123&columns[]=id&columns[]=title&columns[]=content
```

### Create Record

```bash
curl -X POST /api/v1/insert/articles \
  -H "Content-Type: application/json" \
  -d '{
    "changes": {
      "title": "New Article",
      "content": "Article content...",
      "status": "draft"
    }
  }'
```

### Update Record

```bash
curl -X PATCH /api/v1/update/articles \
  -H "Content-Type: application/json" \
  -d '{
    "id": 123,
    "changes": {
      "title": "Updated Title",
      "status": "published"
    }
  }'
```

### Delete Record

```bash
curl -X DELETE /api/v1/delete/articles \
  -H "Content-Type: application/json" \
  -d '{ "id": 123 }'
```

## Best Practices

### 1. Specify Columns

Always specify the columns you need to optimize query performance:

```json
{
  "columns": ["id", "name", "status"]
}
```

### 2. Use Pagination

Always paginate large datasets:

```json
{
  "pagination": 25,
  "page": 1
}
```

### 3. Filter Early

Apply filters to reduce dataset size before processing:

```json
{
  "filters": [
    { "property": "status", "operator": "=", "value": "active" }
  ]
}
```

### 4. Eager Load Relations

Include relations in request to avoid N+1 queries:

```json
{
  "relations": ["author", "categories"],
  "columns": ["id", "title", "author.name", "categories.name"]
}
```

### 5. Define crudComputedDependencies

For models with computed columns, define dependencies to optimize queries:

```php
public function crudComputedDependencies(): array
{
    return [
        'computed_field' => ['columns' => ['field1', 'field2']],
    ];
}
```

## Related Documentation

- [ACL System](./ACL_SYSTEM.md) - Row-level security
- [Permissions](./PERMISSIONS.md) - Permission system
- [Roles](./ROLES.md) - Role hierarchy
