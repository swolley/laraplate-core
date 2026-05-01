# Core module — platform runtime and cross-cutting capabilities

## Purpose

`Core` is the platform runtime for Laraplate. It provides shared services that every other module depends on: identity, authorization, record lifecycle controls, dynamic entity infrastructure, API tooling, schema inspector, and operational commands.

If a feature exists in multiple modules (security, locking, approvals, translations, generic CRUD), `Core` is usually the owner.

## Capability map

### Authentication and identity

- Laravel Fortify integration (login, password reset, profile, verification).
- Optional social login through Socialite providers (`ENABLE_SOCIAL_LOGIN` / `auth.providers.socialite.enabled`).
- Optional 2FA capability (`ENABLE_USER_2FA`) with Fortify two-factor feature wiring.
- User model includes built-in support for impersonation, approvals, versioning, soft deletes, locks, and role-based permissions.

### Authorization and ACL

- Role/permission system is based on Spatie Permission.
- ACL model provides row-level restrictions through filter payloads.
- ACL resolution logic supports role inheritance fallback, unrestricted ACLs, OR-composition across multiple roles, and cached effective ACL resolution.
- ACL behavior is used by CRUD/query services, not only by UI.

### Record lifecycle controls

- Soft delete support is implemented through custom `SoftDeletes` trait (`deleted_at` + `is_deleted` behavior).
- Versioning is implemented via `HasVersions` and `Version` model, including strategy-based replay/revert behavior.
- Approval flow is implemented with `HasApprovals`, `Modification`, notification services, and preview middleware.
- Preview mode can expose pending modifications before approval through the `preview` request flag and session state.

### Locking and concurrency

- Record lock API (`HasLocks`) supports lock/unlock/toggle and lock-scoped queries.
- Optimistic locking (`HasOptimisticLocking`) supports stale-write prevention through `lock_version`.
- Concurrency exceptions include `AlreadyLockedException`, `CannotUnlockException`, `LockedModelException`, and `StaleModelLockingException`.
- Locking behavior is configurable through `core.locking.*`.

### Dynamic entities and schema inspector

- DynamicEntity model and DynamicEntityService build runtime model metadata from real DB schema.
- Inspector subsystem (`Inspect`, `SchemaInspector`) caches columns/indexes/FKs (persistent cache + in-request memoization).
- CRUD/Grid layers reuse inspector metadata for relations, filters, validation, and field behavior.
- Cache invalidation exists both globally and per entity (including CRUD cache-clear endpoint).

### Settings-driven runtime configuration

Core uses both static config and runtime DB settings:

- Runtime settings examples:
  - `soft_deletes_{table}` in `soft_deletes` group
  - `version_strategy_{table}` in `versioning` group
  - `backendModules` for module activation/deactivation
  - approval notification thresholds in `approvals`
- Config-based examples:
  - Fortify/social/2FA toggles
  - lock column names and lock policy
  - feature exposure flags (`expose_crud_api`, dynamic entities)

Use DB settings for runtime operational switches; use config/env for deployment-level behavior.

### API and Swagger/OpenAPI documentation

- Core provides Swagger/OpenAPI generation and version-aware delivery/merge services.
- Admin-facing pages and routes expose API docs.
- Keep docs in sync with route/schema changes; stale specs are a common operational failure mode.

### License management

- `License` model and `users.license_id` relationship enable per-session or per-user license enforcement.
- Authentication providers (credentials and social) can enforce availability of free licenses.
- Operational commands exist to add/renew/close/free licenses.
- `max_concurrent_sessions` setting is part of runtime control surface.

### Translation and localization stack

- Locale middleware and locale scope support per-locale data access with fallback.
- Translation helpers support multi-locale model data and related testing factories.
- Model translatable tooling exists to convert existing models to translation-table architecture.

## Built-in abstractions used by other modules

Core exposes reusable primitives for module authors:

- UI/resource helpers (`HasTable`, `HasRecords`).
- Data lifecycle traits (`HasVersions`, `SoftDeletes`, `HasLocks`, `HasApprovals`).
- Taxonomy/preset/field-oriented abstractions and dynamic object composition patterns.
- Shared query/filter/sort casting and CRUD request DTO layer.

When building new module features, reuse these primitives instead of re-implementing lifecycle logic.

## Core command catalog (developer operations)

### Identity, permissions, approvals

- `auth:create-user`
- `permission:refresh`
- `approvals:check-pending`

### Licenses

- `auth:licenses`
- `auth:free-all-licenses`
- `auth:free-expired-licenses`

### Locking and concurrency

- `lock:refresh`
- `lock:locked-add` / `lock:locked-remove` (aliases typically exposed as `lock:add` / `lock:remove`)
- `lock:optimistic-add` / `lock:optimistic-remove`

### Soft delete lifecycle

- `model:clear-expired`
- `model:soft-deletes-add`
- `model:soft-deletes-remove`
- `model:soft-deletes-refresh`

### Translation and translatable conversion

- `make:model-translatable`
- `make:translation`
- `lang:check-translations`

### Inspector and dynamic schema cache

- `inspector:warm`
- entity cache clear via CRUD route (`cache-clear/{entity}`)

### API docs

- Swagger generation command (module-specific command wiring in Core console)

## How to use Core capabilities correctly

### For product/admin teams

- Manage users/roles/ACL/settings in Filament resources.
- Use approval queues and preview when moderation is enabled.
- Keep module activation and runtime settings under change-control.

### For API/front-end teams

- Use `/crud` and `/crud/grid` with permission-aware query behavior.
- Handle lock/version conflicts explicitly in UX (retry/reload patterns).
- Consume Swagger docs generated by Core as source of API contract.

### For module developers

- Prefer Core lifecycle traits over custom ad-hoc implementations.
- Use settings groups for runtime switches when behavior must be configurable per table/module.
- Reuse inspector-driven metadata and avoid hardcoded schema assumptions.

## Locking toggle design note (planned extension)

The platform already supports runtime toggles for soft deletes and versioning per table.  
A similar runtime toggle for locking (`locking_{table}`) is under evaluation to provide parity, with strict safeguards to avoid accidental concurrency regressions.

## Troubleshooting quick guide

- Permission mismatch despite role assignment: refresh permissions and verify ACL chain/inheritance.
- Unexpected missing records: check soft-delete scopes and preview mode.
- Revert/rollback confusion: verify version strategy (`DIFF` vs `SNAPSHOT`) for the table.
- Concurrent update failures: inspect lock status and `lock_version` mismatch path.
- Dynamic entity metadata stale: warm or clear inspector caches.
- API docs outdated: regenerate Swagger/OpenAPI and verify version merge outputs.

## FAQ prompts for RAG

- How do ACL filters merge when a user has multiple roles?
- What is the difference between record lock and optimistic lock in Core?
- How do I rollback a record to a previous version?
- How can I disable soft deletes for one specific table at runtime?
- How are pending approvals previewed before final approval?
- How does module activation through settings work?
- How do I regenerate and publish Swagger docs after route changes?
- How do license checks affect login for normal users versus superadmins?
- How do I convert an existing model to translation-table architecture?
- What should I clear when dynamic entity metadata looks outdated?