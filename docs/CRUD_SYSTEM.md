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

#### Relation sync on update

`update` writes only fillable columns. To reassign many-to-many relations in the same
call, send a `relations` map: `{ "id": 5, "relations": { "tags": [1,2], "categories": [] } }`.
A model opts in by implementing `Contracts\ProvidesSyncableRelations::syncableRelations()`,
which whitelists the relations a client may `sync` — that whitelist is the authorization
boundary. `CrudService::update` rejects any relation not whitelisted or not a
`BelongsToMany`/`MorphToMany` with `422`/`400`, then syncs each present relation in the
update transaction (a present key syncs wholesale, empty clears, an omitted key is untouched).

#### Role-scoped ACLs and dynamic placeholders

An `acls.role_id` (nullable) scopes an ACL to a single role: set, it applies only to that
role (paired with `permission_id`, i.e. the `role_has_permissions` pair); null keeps the
legacy behavior (applies to every role holding the permission). This lets two roles that
share a permission carry different row-level filters — e.g. the anonymous `guest` role is
restricted while staff roles are not.

ACL filter values may use dynamic placeholders resolved at query-build time: `@now` and
`@today` become the live `Carbon::now()` / `Carbon::today()`. This is what lets a stored
ACL express a moving publication window.

Together these replace what used to be a per-model validity global scope: `CMS\Content`
publication filtering is a `guest`-scoped ACL on `cms_contents.select` whose `valid_from <=
@now AND (valid_to >= @now OR valid_to IS NULL)` filter limits the public reader to
published content, while staff (no such ACL) read everything.

Role inheritance never leaks an unwanted restriction: a role with its own ACL for a
permission uses it and ignores any ancestor ACL. So a child role that would otherwise
inherit a restrictive parent ACL can carry its own higher-priority `unrestricted = true`
ACL — which contributes no filters — to override the inherited one and read everything.

Effective ACLs are cached per user/permission (`AclResolverService`, one-hour TTL).
`AclObserver` flushes that cache on every ACL create/update/delete/restore, so an ACL
change (including flipping `unrestricted`) takes effect immediately instead of after the
TTL expires.

### Internal-only Operations

These are registered in `routes/web.php`, not in the shared `routes/crud.php`. They are
therefore reachable on the session-based `/app` surface only and are never exposed on
`/api/v1`, regardless of `core.expose_crud_api`.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/app/crud/activate/{module}/{entity}` | PATCH | Restore soft-deleted record |
| `/app/crud/inactivate/{module}/{entity}` | PATCH | Soft delete record |
| `/app/crud/approve/{module}/{entity}` | PATCH | Approve pending modification |
| `/app/crud/disapprove/{module}/{entity}` | PATCH | Reject pending modification |
| `/app/crud/lock/{module}/{entity}` | PATCH | Lock record for editing |
| `/app/crud/unlock/{module}/{entity}` | PATCH | Unlock record |

Operation pairs share a single permission: `approve` governs `disapprove`, and `lock`
governs `unlock`. Requesting an operation whose target state already holds — locking a
locked record, unlocking an unlocked one — returns `304 Not Modified`.

`activate`/`inactivate` are the exception: they do **not** share a permission. `activate`
restores a soft-deleted record and requires the `restore` permission; `inactivate`
soft-deletes a live record and requires the `delete` permission (hard delete via
`/api/v1/delete` and `/app/crud/delete` requires `forceDelete`).

### Domain Actions

The verbs above are generic: they act on structures Core attaches to any record. Modules
also need verbs that act on the record itself — posting an invoice, closing a fiscal
period, reversing a journal entry. One route serves all of them:

```
POST /app/crud/{action}/{module}/{entity}      id and action payload in the body
```

`POST` rather than `PATCH` because a domain action invokes an operation rather than
patching a representation, and several are not idempotent.

The route is declared **last** in Core's `crud` group. Laravel matches in registration
order with no notion of specificity, so every literal verb above must be tried first; the
grid and graph groups carry an extra path segment and never reach it. Use
`php artisan route:check <url> --method=POST` to see which route actually answers a URL —
`route:list` sorts by URI and hides the ordering that decides the match.

#### The registry decides what exists

A module registers its actions at boot; the route table knows nothing about them:

```php
$registry->register(Invoice::class, 'post', function (Model $record, array $payload, User $user): Model {
    $record->update(['posted_at' => now()]);

    return $record->fresh();
});
```

Handlers stay thin. Business rules, locking and state guards belong to the services and
policies the module already has — a handler that added a rule of its own would create a
second truth. An action nobody registered answers `404`, not `403`: replying `403` would
claim it exists.

#### Authorization goes through the policy

Generic CRUD authorizes on a permission name alone. Domain actions authorize through the
`Gate`, because their guard is intrinsic: posting an already-posted invoice is not a
permission problem. The module policy combines the state predicate with the permission,
and `force_post` in snake_case resolves to the `forcePost` policy method.

A model must therefore have a registered policy for its domain actions to be reachable.
Without one the Gate has nothing to consult and denies.

#### Overriding a generic verb

Where a module needs a generic verb to mean something else for one entity, the model
declares it:

```php
final class ReturnOrder extends Model implements OverridesGenericCrudActions
{
    public static function overriddenCrudActions(): array
    {
        return ['approve'];
    }
}
```

Core's `approve` votes on a pending `Modification`; `ReturnOrder`'s advances the document
from Draft to Approved. Only one meaning can win per entity, so `DomainActionRegistry`
refuses to register a generic verb unless the model declares the override, and refuses it
outright if the model also uses the trait giving that verb its generic meaning
(`HasApprovals` for `approve`/`disapprove`, `HasLocks` for `lock`/`unlock`, `SoftDeletes`
for `activate`/`inactivate`). The check runs at registration, which happens at boot, so a
contradiction stops the application on start rather than surfacing when one record is
first touched.

#### Responses

A handler returning a `Symfony\Component\HttpFoundation\Response` is passed through
untouched — that is how file exports stream and how multipart uploads are consumed.
Anything else is wrapped in a `CrudResult`, so a domain action looks like every other CRUD
response. Authorization and the state guard run before the first byte, so a refusal is
still a JSON error rather than a corrupt download.

`ValidationException` maps to `422` and `DomainException` to `409`.

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

A "dotless" aggregate names a relation on the main model itself: `{ "name": "comments", "type": "count" }` runs `withCount('comments')` and adds a `comments_count` attribute to each row (`<relation>_count`; `sum`/`avg`/… follow Eloquent's `<relation>_<agg>_<column>` naming). It applies whether or not other relations are eager-loaded. A dotted aggregate (`author.comments:count`) instead counts a sub-relation inside the loaded `author` relation. Columns are namespaced to the model's real table, so this works for entities whose route alias differs from their table (e.g. the `locations` alias over `cms_locations`). An aggregate whose name is not a real relation fails fast with a clear error. Each relation-count subquery is constrained by the related entity's read ACL, so a `*_count` never counts rows the viewer is not permitted to see.

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
"articles"  → Modules\CMS\Models\Article
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

Lock/unlock via `PATCH /app/crud/lock/{module}/{entity}` and `PATCH /app/crud/unlock/{module}/{entity}`.
Both are governed by the `{connection}.{table}.lock` permission.

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

## Faceted Counts

The standalone `crud/facets/{module}/{entity}` endpoint reuses the whole list
vocabulary (`filters`, `sort`, `pagination`) and serves two shapes on one route:

- **Tier 1 — enumerable counts.** Send `columns[]`; each column becomes a flat
  facet dimension returning every distinct value with a `total`/`count` pair.
- **Tier 2 — open facet.** Send a singular `facet` object to page, search and sort
  one high-cardinality dimension. The double counter reports `total` (ACL only)
  next to `count` (ACL + the request filters, minus the facet's own selection so
  cross-filtering stays live).

`facet` fields:

| Field | Meaning |
| --- | --- |
| `groupBy` | Key column grouped and counted on (e.g. `category_id`). |
| `fields[]` | Display fields resolved per key: base columns, or single-hop `relation.column`. |
| `labelField` | A label to search/sort by instead of the raw key; enables `label_asc`/`label_desc`. |
| `relation` | A BelongsToMany/MorphToMany relation to facet over its pivot instead of a base column. |
| `groupBy: relation.column` | A single-hop to-one (`BelongsTo`) column to group by via a join (e.g. `place.country`); the value is its own label. |
| `page`, `perPage`, `search`, `sort` | The facet's own window, value search and ordering. |

### Label resolution

A facet key labels through one of three sources, decided before the query by
whether the label is materialised in a DB column:

1. **Base column** — the key column itself, or a base column on the grouped model.
2. **Foreign-key label** — a single-hop `BelongsTo` keyed by `groupBy`
   (`license_id` → `license.uuid`). When the key is exposed only through an
   accessor (no `BelongsTo`), a model declares the mapping via
   `ProvidesFacetLabelSources::facetLabelSources()`, returning a `FacetLabelSource`
   (related class + foreign key). Content maps `entity` → `entity_id` this way so
   the content type labels from the entity name. A declared source wins over a
   same-named `BelongsTo`, and may point the label at the related model's
   locale-scoped translation (`translationRelation` + `translationColumn`) — so a
   base-column FK facet can label, search and sort by a **translated** name, keyed
   by the group key. Category maps `parent` → `parent_id` labelled from the parent's
   translated `name`.
3. **Translated label** — for a relation facet, a `relation.column` label field
   whose relation is the related model's `HasMany` translation relation
   (`translations.name`) is joined locale-scoped, enabling display, search and
   sort by the translated name.

Labels that live only in a PHP accessor (not materialised in any DB column) cannot
be searched or sorted; expose the underlying column through one of the sources
above to facet by it.

A facet's `groupBy` (and `relation`) is validated up front: if it resolves to a
column that does not exist — e.g. a magic accessor whose value lives on another
table, like `country` on a `HasPlace` model — the request fails fast with a clear
error naming the real path to use (`place.country`), never a cryptic SQL error.

### Related-column facets

A dotted `groupBy` such as `place.country` groups over a column reached through a
single-hop `BelongsTo`: the parent rows are joined to the related table and grouped
by the related column, counting distinct parents per value (the value is its own
label). This facets a list by a column that lives one hop away — e.g. Locations by
their place's country/province. Its selection folds back as a `relation.column`
membership filter.

### Relation facets

With `relation` set, keys are the related model ids and the double counter counts
distinct parent rows per related key. Parent ACL and filters are enforced through
a bounded id subquery (never a join into the aggregated query, so parent scopes
never collide with related columns), the MorphToMany morph constraint is applied,
and related soft-deletes are honoured. Content facets `categories` and `tags` this
way.

## Related Documentation

- [ACL System](./ACL_SYSTEM.md) - Row-level security
- [Permissions](./PERMISSIONS.md) - Permission system
- [Roles](./ROLES.md) - Role hierarchy
