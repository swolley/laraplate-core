# Core Graph System

Core Graph is the CRUD-aligned graph layer for Laraplate. It is owned by the Core module and is available for every CRUD-resolvable entity unless normal CRUD resolution, authorization, validation, or optional provider rules reject the request. CMS is the first provider and consumer, but it does not own traversal, authorization, request parsing, or response contracts.

## Public Routes

API routes are mounted under the CRUD namespace because graph operations extend the CRUD model rather than introducing a separate module API surface:

| Operation | API route | Web route |
| --- | --- | --- |
| Expand one record | `GET /api/v1/crud/graph/expand/{module}/{entity}/{id}` | `GET /app/crud/graph/expand/{module}/{entity}/{id}` |
| Search graph seeds | `GET /api/v1/crud/graph/search/{module}/{entity}` | `GET /app/crud/graph/search/{module}/{entity}` |
| Stats for one expansion | `GET /api/v1/crud/graph/stats/{module}/{entity}/{id}` | `GET /app/crud/graph/stats/{module}/{entity}/{id}` |

`expand` is a detail extension: it starts from one authorized center record and can traverse requested relations. `search` is a search extension: it reuses CRUD search through `CrudService::search()` and returns graph-compatible nodes for search results, optionally expanding requested relations from each seed. `stats` derives counts from the same authorized graph expansion that `expand` would return.

## Request Semantics

`ExpandGraphRequest` extends `DetailRequest`. Supported graph parameters are:

- `relations[]`: explicit relation paths to traverse, such as `tags`, `categories.children`, or `locations`.
- `depth`: maximum relation path depth.
- `limit`: maximum total nodes in an expand response.
- `relation_limit`: maximum targets loaded per relation step.
- `node_detail`: `minimal`, `summary`, or `full`.

`SearchGraphRequest` extends `SearchRequest`. It keeps CRUD search semantics, including `qs`, `mode`, pagination, filters, sorts, and `limit` as the search result limit. Graph-specific search parameters are additive: `relations[]`, `depth`, `relation_limit`, and `node_detail`. Search responses use `center: null` because there can be multiple search-result seeds.

Relation paths are never auto-discovered. If `relations[]` is present, only those paths are traversed. If it is absent, Core asks the optional provider for default relations. If no provider defaults exist, expand returns only the center node and search returns only graph-compatible search result nodes.

## Authorization And Filtering

Graph uses the same `AuthorizationService` model as CRUD. If the user cannot view the center record of an expand or stats request, the request fails like CRUD detail. During traversal, unauthorized neighbor nodes are omitted and `graphMeta.filteredByAcl` is set. Cross-module traversal uses the target node's real module/entity identity and that entity's CRUD permission rules.

Invalid relation paths, excluded relations, provider rule violations, and depth violations return validation-style errors. Truncated graph responses remain successful but set `graphMeta.truncated` and `graphMeta.truncatedBy` so callers do not treat partial graphs as complete.

## Providers

Providers are optional refinements. A module can register a `GraphProviderInterface` implementation in the `GraphProviderRegistryInterface` for a whole module or for a specific entity. Providers can supply default relations, summary fields, edge labels, and excluded relations.

A provider can also implement `GraphProviderRulesInterface` to restrict otherwise generic behavior:

- `allowedRelationPaths()` limits accepted explicit relation paths.
- `maxDepth()` narrows maximum traversal depth for a module/entity.
- `maxRelationLimit()` limits the number of targets for a specific source entity relation.

Providers are not required for graph availability. Without a provider, Core uses CRUD entity resolution, generic serialization, explicit `relations[]`, and global graph config limits.

## Response Shape

Graph responses expose:

- `center`: node id for expand/stats, or `null` for search.
- `nodes`: graph nodes identified as `{module}:{entity}:{id}`.
- `edges`: deterministic relation edges.
- `graphMeta`: requested relations, depth, truncation, ACL filtering, cycles, and deduplication metadata.
- `searchMeta`: search-only metadata for graph search.
- `stats`: stats-only counts for graph stats.

Node details are controlled by `node_detail`. Summary serialization uses provider summary fields when available and generic model attributes otherwise.

## Performance Boundary

Runtime traversal is the correctness baseline for expand, search, and stats. Do not add materialized edge storage until a real workflow shows runtime traversal is too expensive and the affected module/entity/relation set has an accepted invalidation and freshness strategy. Any future materialized layer must preserve the public response contract and fall back to runtime traversal whenever freshness cannot be proven.

## Tests

Graph coverage lives under:

- `Modules/Core/tests/Feature/Graph/`
- `Modules/CMS/tests/Feature/Graph/`

Run graph-focused checks with:

```bash
rtk php artisan test --compact Modules/Core/tests/Feature/Graph Modules/CMS/tests/Feature/Graph
```

Run affected CRUD request checks with:

```bash
rtk php artisan test --compact Modules/Core/tests/Integration/Http/Requests/AuthAndSearchRequestsTest.php Modules/Core/tests/Feature/Api/CrudApiTest.php
```
