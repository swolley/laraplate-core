<?php

declare(strict_types=1);

namespace Modules\Core\Import\Enums;

/**
 * The lifecycle of an interactive bulk import.
 *
 * `Draft` — file uploaded, columns detected, awaiting a column mapping.
 * `Queued` — mapping saved and the processing job dispatched.
 * `Processing` — the job is streaming and upserting rows.
 * `Completed` — every row was processed (some may have failed per-row).
 * `Failed` — the job itself aborted before finishing the stream.
 */
enum ImportSessionStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether the session has reached an outcome and will not change further.
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
