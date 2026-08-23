<?php

declare(strict_types=1);

namespace Modules\Core\Import\Enums;

/**
 * What an entity importer did with one mapped row. A per-row failure is not an
 * outcome — it is raised as a {@see \Modules\Core\Import\Exceptions\RowImportException}
 * and recorded separately, so the counters here only ever describe successes.
 */
enum ImportRowOutcome: string
{
    /**
     * A new record was created.
     */
    case Created = 'created';

    /**
     * An existing record (matched by its external identity) was updated.
     */
    case Updated = 'updated';

    /**
     * The row was intentionally left as-is (e.g. unchanged fingerprint).
     */
    case Skipped = 'skipped';
}
