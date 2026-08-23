<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

use Modules\Core\Import\Enums\ImportRowOutcome;
use Modules\Core\Import\Support\ImportRowContext;
use Modules\Core\Import\ValueObjects\ImportField;

/**
 * A per-entity target for the generic bulk import: it declares the fields a mapped
 * source row may fill, and upserts one already-mapped row into its own domain,
 * owning validation, the create/update decision and idempotency (typically via
 * {@see \Modules\Core\Import\Support\RecordOriginRegistry}).
 *
 * Importers register in an open registry keyed by {@see key()}, so a module — or a
 * third-party package — adds an importable entity without the framework knowing it.
 * The framework never imports an arbitrary table: only a registered entity is
 * importable.
 */
interface EntityImporterInterface
{
    /**
     * Stable, unique identifier, namespaced by module (e.g. `core.user`, `sao.ticket`).
     */
    public function key(): string;

    /**
     * Human-readable name for the entity list.
     */
    public function label(): string;

    /**
     * The target fields a source column may map to.
     *
     * @return list<ImportField>
     */
    public function fields(): array;

    /**
     * Upsert one mapped row (keyed by field name). Return the outcome, or raise a
     * {@see \Modules\Core\Import\Exceptions\RowImportException} for a per-row
     * failure the framework should record and skip. Runs inside a per-row savepoint,
     * so raising rolls the row's own writes back cleanly.
     *
     * @param  array<string, string>  $row
     */
    public function import(array $row, ImportRowContext $context): ImportRowOutcome;
}
