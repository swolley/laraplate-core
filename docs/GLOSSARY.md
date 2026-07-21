# Core module glossary

Canonical English names for Core platform entities. Use these terms in code, APIs, and cross-module documentation.

## Identity and access

| Term | Meaning |
|------|---------|
| **User** | Authenticated principal; Fortify + Sanctum + optional 2FA and social login. |
| **Role** | Hierarchical Spatie role (`parent_id` inheritance). |
| **Permission** | Operation-level grant (e.g. `default.orders.select`). |
| **ACL** | Row-level filter attached to a permission; restricts which records match. |
| **AuthorizationService** | Checks permissions and injects/applies ACL filters on queries. |
| **AclResolverService** | Resolves effective ACLs for a user/permission; combines filters with OR logic. |
| **unrestricted** | ACL flag: user sees all rows for that permission (no filter). |
| **License** | Seat / entitlement record gating module or feature access. |

### Permission vs ACL

| Component | Question answered | Scope |
|-----------|-------------------|-------|
| **Permission** | Can the user perform this operation? | Operation (SELECT, INSERT, UPDATE, DELETE) |
| **ACL** | On which records? | Row-level filters on queries |

## CRUD pipeline

| Term | Meaning |
|------|---------|
| **CrudService** | Orchestrates list/detail/insert/update/delete/history/tree operations. |
| **CrudController** | HTTP entry point mapping routes to `CrudService`. |
| **QueryBuilder** | Builds Eloquent queries from `RequestData` (filters, sorts, relations). |
| **DynamicEntity** | Runtime model resolver: maps entity name string to Eloquent class + metadata. |
| **ResponseBuilder** | Formats `CrudResult` to JSON/XML with optional caching. |
| **FiltersGroup** | Nested AND/OR filter tree applied by `QueryBuilder`. |
| **CrudResult** | Service result envelope: data rows + pagination/meta. |

## Dynamic entity stack

| Term | Meaning |
|------|---------|
| **Entity** | Abstract schema owner: defines fields and presets for a business object type. |
| **Preset** | Versioned field layout template under an `Entity`. |
| **Presettable** | Snapshot row binding one `Preset` to one `Entity` at a point in time. |
| **Field** | Column definition (type, validation, UI hints) for dynamic entities. |
| **SchemaInspector** | Introspects database schema for `DynamicEntity` runtime. |

## Lifecycle traits

| Term | Meaning |
|------|---------|
| **SoftDeletes** | Logical delete with `deleted_at`; refresh command syncs DB triggers. |
| **HasVersions** | Immutable version history via `Version` rows. |
| **HasApprovals** | Pending-change workflow via `Modification` + approve/disapprove votes. |
| **Modification** | Diff record for a modifiable model awaiting approval. |
| **HasValidity** | `valid_from` / `valid_to` temporal validity window. |
| **HasLocks** | Application-level record lock on business events. |
| **HasOptimisticLocking** | Concurrency guard via version column. |

## Event orchestration (cross-module bus)

| Term | Meaning |
|------|---------|
| **ModelRequiresIndexing** | Emitted by `Searchable` when a model should be indexed. |
| **IndexInSearchJob** | Core job writing documents to the configured search engine. |
| **ModificationRequiresModeration** | Emitted when a new `Modification` needs optional AI pre-processing. |
| **ModelPreProcessingCompleted** | Signals one pre-processing step finished (embeddings, translation, AI vote). |
| **FinalizeModelIndexingListener** | Dispatches `IndexInSearchJob` when all pre-processing steps complete. |
| **ModerationContextBuilderRegistry** | Domain modules register builders; AI resolves at runtime. |
| **IndexModelFallbackListener** | Ensures indexing proceeds when AI does not handle the event. |
| **ModificationModerationFallbackListener** | No-op fallback when AI moderation is skipped. |

## Search

| Term | Meaning |
|------|---------|
| **Searchable** | Trait: `queueMakeSearchable()`, `$embed`, vector search hooks. |
| **Transactional outbox** | `OutboxRecorder` stores durable integration events in `core_outbox_events` within the domain transaction; `PublishOutboxEventJob` delivers them through the replaceable `OutboxPublisher` contract after commit. |
| **SchemaDefinition** | Describes indexable fields and analyzers for a model. |
| **ISearchEngine** | Contract for Elasticsearch, Typesense, or database translator backends. |
| **ModelEmbedding** | Persisted embedding vectors linked to searchable models. |
| **Indexed relation-field filter** | Search filter using a schema-declared dot path such as `tags.id`; it targets relation data already stored in the search document. |
| **Relation anti-exists filter** | `!=` / `not in` on an indexed relation field; means no related indexed row matches the value/list. |
| **TextMatchPreference** | Adaptive caller preference: `auto`, `strict`, `balanced`, or `tolerant`; resolved before engine translation. |
| **TextMatchOptions** | Granular effective text matching contract shared by all engines. |
| **Protected search token** | Acronym, code, UUID, email, number, or short token that remains non-fuzzy unless explicitly enabled. |
| **SearchQueryAnalyzer** | Classifies original query tokens and selects significant/protected/eligible counts for matching policy. |
| **Matching degradation** | Requested portable semantic that the active engine cannot represent exactly. |

## Internationalization

| Term | Meaning |
|------|---------|
| **HasTranslations** | Trait for translatable attribute storage. |
| **LocaleContext** | Request-scoped active locale resolution. |
| **LocaleScope** | Global scope filtering translatable queries by locale. |
| **TranslatedModelSaved** | Event triggering optional auto-translation pipeline. |
| **auto_translate_{table}** | Per-model setting for post-save translation. |

## Geo and places

| Term | Meaning |
|------|---------|
| **Place** | Canonical address/geo record (lat/lng, formatted address). |
| **IGeocodingService** | Contract for Nominatim or Google Maps geocoding adapters. |
| **HasPlace** | Trait linking a model to a `Place` row. |

## Platform settings

| Term | Meaning |
|------|---------|
| **Setting** | Key/value store (often JSON) for runtime configuration. |
| **ModuleDatabaseActivator** | Enables/disables modules via `backendModules` setting. |
| **CronJob** | Scheduled task definition managed in Filament. |

## Related reading

- `docs/ACL_SYSTEM.md` — permission and ACL resolution
- `docs/CRUD_SYSTEM.md` — API endpoints and request flow
- `docs/EVENT_ORCHESTRATION.md` — indexing and moderation orchestration
- `docs/rag/MODULE.md` — RAG-oriented platform overview
