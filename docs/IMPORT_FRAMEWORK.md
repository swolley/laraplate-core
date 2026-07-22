# Module import command framework

Core provides reusable infrastructure for module-owned bulk import commands. It deliberately does not register a runnable `core:import` command: the destination domain must remain visible in each concrete command name, such as `cms:import` or `erp:import`.

## Core components

| Component | Responsibility |
|---|---|
| `AbstractImportCommand` | Common command execution, options, bootstrap loading, interactive plugin selection, output, and exit codes |
| `BulkImporterInterface` | Neutral executable contract returning the imported root-record count |
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
- `--dry-run`: roll back writes on the default database connection;
- `--limit=`: non-negative import limit passed to the importer;
- `--no-search`: disable Scout indexing for the process.

Module marker interfaces should extend `Modules\Core\Import\Contracts\BulkImporterInterface`. Configure the module resolver and discovery with that marker so a command rejects importers targeting another module before execution.

## Domain boundary

Core does not know source schemas, destination entities, import ordering, DTOs, upserters, accounting rules, inventory rules, or conflict policies. A module owns its destination pipeline and an external plugin owns source access and source-specific mapping.

Importers must call module services for protected domain mutations. They must not bypass posting, numbering, locking, inventory, accounting, audit, or authorization rules through raw writes.

## Dry-run guarantee

`BulkImportRunner` opens a transaction on the current default connection and restores its previous transaction nesting level. This rolls back database writes made on that connection only.

It does not roll back other connections, files, object storage, queued work, HTTP calls, or other external side effects. Importers receive `dryRun` as a named constructor parameter and are responsible for suppressing non-transactional effects. The command disables Scout when dry-run is active.

## Imports versus synchronization

This framework executes bounded, operator-triggered imports. Continuous external-system synchronization is a separate layer that may reuse importer pipelines but also requires explicit cursors, remote identity, idempotency, conflict policy, direction, retries, observability, and scheduling. Those concerns must not be added implicitly to `AbstractImportCommand`.
