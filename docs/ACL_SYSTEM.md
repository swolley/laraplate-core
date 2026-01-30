# ACL (Access Control List) System

## Overview

The ACL system provides **row-level security** (RLS) for the application, allowing fine-grained control over which records a user can access based on their roles and permissions.

## Key Concepts

### Permissions vs ACLs

| Component | Purpose | Scope |
|-----------|---------|-------|
| **Permission** | "Can user do this operation?" | Operation-level (SELECT, INSERT, UPDATE, DELETE) |
| **ACL** | "On which records?" | Row-level (filters applied to queries) |

### Example

```
Permission: default.orders.select
  └── "Can user SELECT from orders table?" → Yes/No

ACL attached to permission:
  └── "Which orders can they see?" → filters: [department_id = 5, status = 'active']
```

## Architecture

### Service Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    AuthorizationService                          │
│  ├── checkPermission()      → Can user do this operation?       │
│  ├── ensurePermission()     → Check or throw exception          │
│  ├── getAclFilters()        → Get ACL filters for permission    │
│  ├── injectAclFilters()     → Inject ACL into request filters   │
│  └── applyAclFiltersToQuery() → Apply ACL directly to query     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    AclResolverService                            │
│  ├── getEffectiveAcls()     → Resolve ACLs for user/permission  │
│  ├── getCombinedFilters()   → Combine ACLs with OR logic        │
│  └── hasUnrestrictedAccess() → Check if user has no restrictions│
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    QueryBuilder                                  │
│  ├── prepareQuery()         → Build query from request data     │
│  └── applyFilters()         → Apply FiltersGroup to query       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    CrudService                                   │
│  Orchestrates: AuthorizationService + QueryBuilder               │
│  1. ensurePermission()      → Check permission                  │
│  2. injectAclFilters()      → Inject ACL into request           │
│  3. prepareQuery()          → Build the query                   │
│  4. Execute and return      → Run query, return result          │
└─────────────────────────────────────────────────────────────────┘
```

### Data Model

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER                                     │
│  └── roles[] (hierarchical via parent_id)                       │
│        └── permissions[] (inherited from parent roles)          │
│              └── acls[] (row-level filters)                     │
│                    ├── filters (JSON query builder)             │
│                    ├── unrestricted (bool)                      │
│                    ├── priority (int)                           │
│                    └── enabled (bool)                           │
└─────────────────────────────────────────────────────────────────┘
```

### Database Schema

**Table: `acls`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `permission_id` | bigint | FK to permissions table |
| `filters` | json | Query builder filters (FiltersGroup) |
| `sort` | json | Optional default sorting |
| `description` | varchar(255) | Human-readable description |
| `unrestricted` | boolean | If true, no filters applied (full access) |
| `priority` | smallint | Higher priority evaluated first (default: 0) |
| `enabled` | boolean | If false, ACL is ignored (default: true) |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |
| `deleted_at` | timestamp | Soft delete timestamp |

## Resolution Logic

The `AclResolverService` implements the following resolution rules:

### 1. Permission Inheritance (Additive)

Permissions follow **additive** inheritance through role hierarchy:

```
guest (root)
  └── permissions: [orders.select]
        │
        └── editor (child of guest)
              └── permissions: [orders.select (inherited), orders.insert]
                    │
                    └── admin (child of editor)
                          └── permissions: [all inherited + orders.delete]
```

**Rule**: Child roles inherit ALL permissions from parent roles.

### 2. ACL Resolution (Override)

ACLs follow **override** logic - the most specific wins:

```
guest
  └── Permission: orders.select
        └── ACL: { filters: [status = 'published'] }

editor (child of guest, NO ACL defined)
  └── Inherits ACL from guest → sees only 'published'

admin (child of editor)
  └── ACL: { unrestricted: true }
  └── Sees EVERYTHING (overrides parent)
```

**Rules**:
1. If role has ACL for permission → **use it** (overrides parent)
2. If role has NO ACL → **inherit from parent** role
3. If `unrestricted = true` → ACL is **transparent** (doesn't contribute filters to the query)

### 3. Unrestricted ACLs (Transparent)

ACLs with `unrestricted = true` are **transparent** - they don't contribute filters to the query.
This is useful for breaking inheritance without imposing new restrictions.

```
User "mario"
  ├── Role: sales (ACL: country = 'IT')           → contributes filter
  └── Role: supervisor (ACL: unrestricted = true) → does NOT contribute

Effective filter: country = 'IT'
Mario sees only Italian records.
```

If ALL ACLs are unrestricted (or no ACLs exist), user sees everything:

```
User "admin_user"
  └── Role: admin (ACL: unrestricted = true) → does NOT contribute

Effective filter: NONE
Admin sees all records.
```

**Key point**: `unrestricted = true` does NOT mean "ignore all filters". It means "this role branch doesn't add any restrictions". Other roles' filters still apply.

### 4. Multiple Roles (OR Union)

When a user has multiple non-hierarchical roles with filters, they are combined with **OR**:

```
User "mario"
  ├── Role: sales_it (ACL: country = 'IT')  → contributes
  └── Role: sales_de (ACL: country = 'DE')  → contributes

Effective filter: country = 'IT' OR country = 'DE'
Mario sees records from BOTH countries.
```

### 5. SuperAdmin Bypass

Users with the `superadmin` role bypass ALL ACL filters automatically.

## ACL Filter Injection

When ACL filters are injected into a request, they wrap the existing user filters:

```
Before injection:
  requestData->filters = user_filters

After injection:
  requestData->filters = {
    operator: AND,
    filters: [
      acl_filters,     // ACL constraints (user cannot bypass these)
      user_filters     // Original user filters (if any)
    ]
  }
```

This ensures that:
1. **Users cannot bypass ACL restrictions** - ACL filters are always applied
2. **User filters are additive** - Users can further restrict their view, but never expand it
3. **Filters are processed together** - Single query execution, no N+1 issues

### Example

```php
// User with ACL: department_id = 5
// User requests: status = 'active'

// Result query:
// WHERE (department_id = 5) AND (status = 'active')
```

## Filter Format

Filters use the same format as the CRUD query builder:

```json
{
  "filters": [
    {
      "property": "status",
      "operator": "=",
      "value": "active"
    },
    {
      "property": "department_id",
      "operator": "in",
      "value": [1, 2, 3]
    }
  ],
  "operator": "and"
}
```

### Supported Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` | Equals | `status = 'active'` |
| `!=` | Not equals | `status != 'deleted'` |
| `>` | Greater than | `amount > 100` |
| `>=` | Greater than or equal | `amount >= 100` |
| `<` | Less than | `amount < 100` |
| `<=` | Less than or equal | `amount <= 100` |
| `like` | LIKE pattern | `name like '%John%'` |
| `not like` | NOT LIKE pattern | `name not like '%test%'` |
| `in` | IN list | `status in ['active', 'pending']` |
| `between` | BETWEEN range | `created_at between ['2024-01-01', '2024-12-31']` |

### Nested Filters

Filters can be nested with AND/OR operators:

```json
{
  "filters": [
    {
      "property": "status",
      "operator": "=",
      "value": "active"
    },
    {
      "filters": [
        {
          "property": "department_id",
          "operator": "=",
          "value": 1
        },
        {
          "property": "region",
          "operator": "=",
          "value": "north"
        }
      ],
      "operator": "or"
    }
  ],
  "operator": "and"
}
```

Result: `status = 'active' AND (department_id = 1 OR region = 'north')`

## Usage

### Automatic Integration (CrudService)

ACL filters are automatically applied in `CrudService` for all read operations:
- `list()` - List records (ACL injected into request filters)
- `detail()` - Get single record (ACL applied to query)
- `history()` - Get record history (ACL applied to query)
- `tree()` - Get hierarchical records (ACL applied to query)

The flow for `list()` operations:
1. **Check permission** - Verify user can perform the operation
2. **Inject ACL filters** - Modify `requestData->filters` to include ACL constraints
3. **Build query** - QueryBuilder applies all filters (including ACLs)
4. **Execute** - Run query and return results

```php
// In CrudService::list()
$permission_name = $this->auth->ensurePermission($request, 'orders', 'select');
$this->auth->injectAclFilters($requestData, $permission_name);  // Modifies filters
$this->query_builder->prepareQuery($query, $requestData);       // ACL now included
```

### Manual Integration

For custom queries, use `AuthorizationService`:

```php
use Modules\Core\Services\Authorization\AuthorizationService;

$auth = app(AuthorizationService::class);

// Option 1: Inject into request (for ListRequestData)
$auth->injectAclFilters($requestData, 'default.orders.select');
// Now $requestData->filters includes ACL constraints

// Option 2: Apply directly to query (for custom queries)
$query = Order::query();
$auth->applyAclFiltersToQuery($query, 'default.orders.select');
$orders = $query->get();
```

### Direct AclResolver Usage

For advanced scenarios, use `AclResolverService` directly:

```php
use Modules\Core\Services\AclResolverService;
use Modules\Core\Models\Permission;

$resolver = app(AclResolverService::class);
$permission = Permission::findByName('default.orders.select');

// Get all effective ACLs for user
$acls = $resolver->getEffectiveAcls($user, $permission);

// Get combined filters (returns FiltersGroup or null)
$filters = $resolver->getCombinedFilters($user, $permission);

// Check if user has unrestricted access
$hasFullAccess = $resolver->hasUnrestrictedAccess($user, $permission);
```


## Configuration Examples

### Scenario 1: Department-Based Access

```
Role: sales_dept_1
  └── Permission: default.orders.select
        └── ACL: { filters: [department_id = 1] }

Role: sales_dept_2
  └── Permission: default.orders.select
        └── ACL: { filters: [department_id = 2] }
```

### Scenario 2: Status-Based Access

```
Role: guest
  └── Permission: default.articles.select
        └── ACL: { filters: [status = 'published'] }

Role: editor (child of guest)
  └── Permission: default.articles.select
        └── ACL: { filters: [status IN ('published', 'draft')] }

Role: admin (child of editor)
  └── Permission: default.articles.select
        └── ACL: { unrestricted: true }
```

### Scenario 3: Owner-Based Access

For user-specific access (e.g., "see only your own records"), you would need to implement dynamic filter resolution. This is a planned feature.

```json
{
  "filters": [
    {
      "property": "created_by",
      "operator": "=",
      "value": "{user.id}"
    }
  ]
}
```

> **Note**: Dynamic placeholders like `{user.id}` are not yet implemented. This is documented as a future enhancement.

## Caching

ACL resolutions are cached for performance:

- **Cache key**: `acl:resolved:user:{user_id}:perm:{permission_id}`
- **TTL**: 1 hour (3600 seconds)

### Cache Invalidation

Cache is cleared when:
- User roles change
- ACL records are modified
- Permissions are modified

Use these methods to manually clear cache:

```php
$resolver->clearCacheForUser($user);
$resolver->clearCacheForPermission($permission);
```

## Best Practices

### 1. Design ACLs from Least to Most Permissive

Start with restrictive ACLs at the root role, then expand access down the hierarchy:

```
guest → sees only published
  └── user → sees published + own drafts
        └── editor → sees all statuses
              └── admin → unrestricted
```

### 2. Use `unrestricted` Explicitly

When you want a role to have full access, set `unrestricted: true` explicitly rather than leaving filters empty. This makes the intent clear.

### 3. Document ACL Descriptions

Always fill the `description` field with a human-readable explanation:

```json
{
  "description": "Sales team can only see orders from their assigned region",
  "filters": [...]
}
```

### 4. Use Priority for Conflict Resolution

When multiple ACLs could apply, use `priority` to ensure deterministic behavior:

```
ACL 1: priority = 10, filters = [status = 'active']
ACL 2: priority = 20, filters = [status = 'pending']  ← This wins
```

### 5. Test ACL Logic

Write tests to verify ACL behavior:

```php
it('sales user can only see own department orders', function () {
    $sales_user = User::factory()->create();
    $sales_user->assignRole('sales_dept_1');
    
    actingAs($sales_user);
    
    $response = $this->getJson('/api/v1/select/orders');
    
    $response->assertOk();
    $orders = $response->json('data');
    
    // All orders should be from department 1
    foreach ($orders as $order) {
        expect($order['department_id'])->toBe(1);
    }
});
```

## File Structure

### Services

| File | Purpose |
|------|---------|
| `Services/Authorization/AuthorizationService.php` | Permission checks, ACL injection, query filtering |
| `Services/AclResolverService.php` | Resolves effective ACLs for user/permission with inheritance |
| `Services/Crud/QueryBuilder.php` | Builds Eloquent queries from request data |
| `Services/Crud/CrudService.php` | Orchestrates CRUD operations |

### Models

| File | Purpose |
|------|---------|
| `Models/ACL.php` | ACL model with filters, unrestricted flag, priority |
| `Models/Permission.php` | Spatie permission extended with ACL relationship |
| `Models/Role.php` | Hierarchical role with recursive relationships |

### Migrations

| File | Purpose |
|------|---------|
| `migrations/2025_02_25_225037_make_acl_table.php` | Creates `acls` table |
| `migrations/2026_01_29_100000_add_acl_inheritance_fields.php` | Adds `unrestricted`, `priority`, `enabled` |

## Future Enhancements

1. **Dynamic Placeholders**: Support for `{user.id}`, `{user.department_id}` in filter values
2. **Named Scopes**: Ability to reference Eloquent scopes in ACL configuration
3. **Time-Based ACLs**: ACLs that are only active during certain time periods
4. **Audit Logging**: Track when ACL filters are applied and their impact
5. **Filament UI**: Admin interface for managing ACLs visually

## Related Documentation

- [Permission System](./PERMISSIONS.md)
- [Role Hierarchy](./ROLES.md)
- [CRUD Operations](./CRUD.md)
