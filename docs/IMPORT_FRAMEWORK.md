# Module import command framework

Core provides reusable infrastructure for module-owned bulk import commands. It deliberately does not register a runnable `core:import` command: the destination domain must remain visible in each concrete command name, such as `cms:import` or `erp:import`.

## Core components

| Component | Responsibility |
|---|---|
| `AbstractImportCommand` | Common command execution, options, bootstrap loading, interactive plugin selection, output, and exit codes |
| `BulkImporterInterface` | Neutral executable contract returning the imported root-record count; optional `OutputInterface` for console progress |
| `ConnectionAwareBulkImporterInterface` | Optional contract selecting the destination database connection for dry-run isolation |
| `BulkImporterResolverInterface` | Module-supplied validation and container resolution boundary |
| `ImportPluginDiscoveryInterface` | Module-supplied external plugin discovery boundary |
| `ContainerBulkImporterResolver` | Reusable container resolver parameterized by the accepted importer marker interface |
| `FilesystemImportPluginDiscovery` | Reusable Composer-loaded plugin scan parameterized by root and accepted contract |
| `BulkImportRunner` | Normal execution or default-connection transactional dry-run |

The abstract command is excluded from automatic module command discovery because only instantiable console commands are registered.

## Concrete module commands

A module command extends `AbstractImportCommand`, declares `$name` and `$description`, and injects module-aware resolver/discovery collaborators:

```php
final class ImportCommand extends AbstractImportCommand
{
    protected $name = 'example:import';

    protected $description = 'Import example records <fg=green>(Modules\\Example)</fg=green>';
}
```

Do not declare `$signature`. Core defines the shared options through `getOptions()`:

- `--importer=`: concrete importer FQCN;
- `--bootstrap=`: optional external Composer autoloader;
- `--arg=*`: repeatable importer constructor argument in `key=value` form;
- `--dry-run`: roll back writes on the connection declared by the importer, or the default connection;
- `--limit=`: non-negative import limit passed to the importer;
- `--no-search`: disable Scout indexing for the process.

Module marker interfaces should extend `Modules\Core\Import\Contracts\BulkImporterInterface`. Configure the module resolver and discovery with that marker so a command rejects importers targeting another module before execution.

## Domain boundary

Core does not know source schemas, destination entities, import ordering, DTOs, upserters, accounting rules, inventory rules, or conflict policies. A module owns its destination pipeline and an external plugin owns source access and source-specific mapping.

Importers must call module services for protected domain mutations. They must not bypass posting, numbering, locking, inventory, accounting, audit, or authorization rules through raw writes.

## Dry-run guarantee

`BulkImportRunner` opens a transaction on the connection returned by an optional `ConnectionAwareBulkImporterInterface`, falling back to the current default connection, and restores its previous transaction nesting level. This rolls back database writes made on that connection only.

It does not roll back other connections, files, object storage, queued work, HTTP calls, or other external side effects. Importers receive `dryRun` as a named constructor parameter and are responsible for suppressing non-transactional effects. The command disables Scout when dry-run is active.

## Imports versus synchronization

This framework executes bounded, operator-triggered imports. Continuous external-system synchronization is a separate layer that may reuse importer pipelines but also requires explicit cursors, remote identity, idempotency, conflict policy, direction, retries, observability, and scheduling. Those concerns must not be added implicitly to `AbstractImportCommand`.

## External record identities

`RecordOriginRegistry` is the source-neutral persistence boundary for imported record identities. An identity is the tuple `(referable_type, source_key, external_id)`; adapters should qualify `source_key` by source instance or group when the upstream identifier is not globally unique.

Adapters compute a lowercase SHA-256 fingerprint from normalized source fields and may supply the source modification timestamp as evidence. The registry reports `Missing`, `Unchanged`, or `Changed`, stores provenance on the referable model's database connection, and never decides whether the destination record may be changed. Destination modules own that policy. Source timestamps never replace destination `created_at` or `updated_at` values.

## Interactive bulk import (upload → map → preview → run)

Distinct from the CLI framework above, Core also provides an **interactive**, entity-agnostic bulk import: a user uploads a file, maps its columns to a target entity's fields through dropdowns with a spreadsheet-like preview, and runs the import as a queued job. It is surfaced by the SPA (`/app/crud/imports`) and a Filament monitoring resource.

| Component | Responsibility |
|---|---|
| `EntityImporterInterface` + `EntityImporterRegistry` | The open, per-entity contract (`key`, `label`, `fields`, `import(row, ctx)`) and the singleton registry modules register into from their provider's `boot`. Only registered entities are importable. |
| `ImportField` | One mappable target field a source column can map to (`name`, `label`, `required`, `aliases`). |
| `SourceReaderInterface` + `SourceReaderFactory` | Streaming readers per `ImportSourceFormat`: CSV (`league/csv`), XLSX/ODS (`openspout`), JSON (in-process). SQL is deliberately deferred. |
| `ImportPreviewService` | Detected columns, sample rows, target fields and a header auto-matched mapping suggestion. |
| `ImportSession` / `ImportRowError` | The durable record (`core_import_sessions`): mapping, status, per-outcome counters; and the per-row failure report (`core_import_row_errors`). |
| `ImportRunner` + `ProcessImportSessionJob` | Streams the source, per-chunk commit (durable progress), each row in its own savepoint so a failing row rolls back only itself and lands in the report; fires `ImportSessionCompleted` / `ImportSessionFailed` (the seam for the future in-app notification tray). |
| `ImportLauncher` | The shared "every required field must be mapped before running, then queue" rule, used by both the API controller and the Filament run action. |
| `ImportRelationField` + `RelationValueResolver` | Declares a mapped column that resolves to one or more **related** records by their natural key (slug/name/code), and the reusable engine that turns a possibly multi-value cell into a list of ids. The importer supplies only two domain callbacks — how to find a token, and (for `onMissing: create`) how to create it — while the framework owns splitting, trimming, de-duplication and the missing-token policy. |

An entity importer validates the mapped row, upserts idempotently (typically via `RecordOriginRegistry`), and returns `Created`/`Updated`/`Skipped` or raises `RowImportException` for a per-row failure. Registered importers today:

| Key | Module | Natural key / dedupe | Notes |
|---|---|---|---|
| `core.user` | Core | email | Reference importer. |
| `sao.ticket` | SAO | `TicketLink` / `RecordOrigin` external id | The tracker "file-dump" path. |
| `cms.tag` | CMS | translated name within `type` | Name is a per-locale translation. |
| `cms.contributor` | CMS | name (unique) | Anchored to the `contributors` entity's default preset. |
| `cms.category` | CMS | slug | Hierarchy via an optional `parent` column (slug/name). |
| `cms.content` | CMS | translated slug | Attaches `tags` (by name, created on the fly), `categories` and `contributors` (by slug/name, must pre-exist) from multi-value columns via `RelationValueResolver`. |
| `erp.item` | ERP | `(company, sku)` | Materials/products; company from a column or the active company context. |
| `erp.party` | ERP | `(company, vat)` or `(company, name)` | Customers/suppliers via the `is_customer`/`is_supplier` flags. |

A module adds an importable entity by implementing `EntityImporterInterface` and registering it from its provider's `boot`; the framework never touches an arbitrary table.

### Relations by natural key

Foreign keys are never expressed as internal ids in a source file — a row references a related record by the same human-readable natural key an operator would type (a slug, name or code). A to-one relation resolves that key inline (e.g. `cms.category.parent`, `erp.item.company`). A to-many or cross-entity relation is declared as an `ImportRelationField` and resolved through `RelationValueResolver`, which splits a multi-value cell (`"news, politics"`), de-duplicates the tokens, and applies the field's `OnMissingRelation` policy to any token that matches nothing:

- `create` — provision the related record from the token (tags, cheap folksonomy);
- `error` — fail the row with a per-field message (categories, contributors: curated, so import them first);
- `skip` — silently drop the unmatched token.

`cms.content` is the reference implementation: it attaches tags, categories and contributors in the same row without the file ever carrying an id.
