---
module: core
audience: developer
---
# Interactive bulk import — developer and operator guide

Entity-agnostic, upload-driven bulk import: upload → map → preview → queued run → per-row failure report → in-app notification. Distinct from the CLI `AbstractImportCommand` framework. The human-facing design reference is `Modules/Core/docs/IMPORT_FRAMEWORK.md`; this file is the assistant-oriented summary.

## Pipeline

```text
upload  -> ImportSession (core_import_sessions, status=draft) + SourceReaderFactory column detection
map     -> ImportPreviewService (columns, sample rows, target fields, suggested mapping)
launch  -> ImportLauncher (every required field mapped) -> ProcessImportSessionJob (queued)
run     -> ImportRunner: stream source, per-chunk commit, per-row savepoint
             success -> EntityImporterInterface::import(row, ctx) -> Created|Updated|Skipped
             failure -> RowImportException -> core_import_row_errors (report)
done    -> ImportSessionCompleted | ImportSessionFailed
notify  -> SendImportFinishedNotification -> ImportFinishedNotification (database channel)
```

## Components

| Component | Responsibility |
|---|---|
| `EntityImporterInterface` + `EntityImporterRegistry` | Per-entity contract (`key`, `label`, `fields`, `import(row, ctx)`) and the open singleton registry modules register into from their provider `boot`. Only registered entities are importable. |
| `ImportField` / `ImportRelationField` | A mappable target field; the relation variant additionally carries `multiple`, `separator` and an `OnMissingRelation` policy, surfaced in the serialized field payload so the SPA renders relation columns distinctly. |
| `SourceReaderInterface` + `SourceReaderFactory` | Streaming readers per format: CSV (`league/csv`), XLSX/ODS (`openspout`), JSON. |
| `ImportPreviewService` | Detected columns, sample rows, target fields, header auto-match. |
| `ImportRunner` + `ProcessImportSessionJob` | Per-chunk commit (durable progress), each row in its own savepoint; fires the terminal events. |
| `RelationValueResolver` | Splits a multi-value cell, de-duplicates tokens, applies the missing-token policy; importer supplies find (and, for `create`, create) callbacks. |
| `RecordOriginRegistry` | Idempotent dedupe by external identity `(referable_type, source_key, external_id)`. |

## Relations by natural key

Foreign keys never appear as internal ids in the source. To-one relations resolve inline (`cms.category.parent`, `erp.item.company`). To-many / cross-entity relations use `ImportRelationField` + `RelationValueResolver` with `OnMissingRelation`:

- `Create` — provision the related record from the token (needs a create callback);
- `Error` — fail the row with a per-field `RowImportException`;
- `Skip` — drop the unmatched token.

`cms.content` is the reference: it attaches tags (create), categories and contributors (error, must pre-exist) from multi-value columns. Lookups load the full related row (not a single column), so the dynamic-content models' auto-eager-loaded relations do not trip strict attribute access.

## Notifications

`SendImportFinishedNotification` listens on both terminal events (registered in `CoreServiceProvider::registerImportListeners`) and notifies `ImportSession::user` (skipped when null) with `ImportFinishedNotification` over the **database** channel.

- Table: framework-standard `notifications` (unprefixed) so Laravel's Notifiable and Filament's native bell both find it, plus one derived column `module_name`.
- `Modules\Core\Models\Notification extends DatabaseNotification` mirrors `data->scope` into `module_name` on save (portable pgsql/sqlite; no DB generated column) and adds a `forModule` scope. `User::notifications()` is overridden to read/write through it.
- Payload (`toArray`): `type=import.finished`, `level` (success|warning|danger), `scope` (module from `entity_key` prefix), English `title`/`body` fallback, a **semantic** `action` (`{ target: 'import_session', id, view }`), and a `meta` counts bag the SPA localizes.
- Endpoints (auth-bound, user-scoped): `GET /app/notifications` (optional `?scope=`) → `{ data, meta.unread }`, `GET .../unread-count`, `POST .../{id}/read`, `POST .../read-all`.
- Filament: `->databaseNotifications()->databaseNotificationsPolling('30s')` on the admin panel — same table, zero bespoke UI.
- SPA (ui-core): `createNotificationsClient` (wired into `createLaraplateClient` as `notifications`), `useNotifications` composable (list, unread badge, read mutations, ~30s unread poll auto-stopped on scope dispose), and `NotificationBell` mounted once in the shared `AppShell` (prop `notifications` + `notificationScope`, emits `notification-select` for the app to resolve to its own route).
- Delivery is **polling** on both surfaces; real-time broadcast (Echo + `ShouldBroadcast`) is an additive future upgrade.

## Adding an importable entity

Implement `EntityImporterInterface`, declare its `fields()` (add `ImportRelationField::toField()` for relation columns), upsert idempotently in `import(row, ctx)` returning an `ImportRowOutcome` or raising `RowImportException`, and register it from the module provider's `boot`. Registered today: `core.user`, `sao.ticket`, `cms.tag`, `cms.contributor`, `cms.category`, `cms.content`, `erp.item`, `erp.party`.
