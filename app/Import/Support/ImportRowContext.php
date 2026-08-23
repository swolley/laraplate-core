<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Modules\Core\Models\ImportSession;

/**
 * The context handed to an entity importer for one row: which session is running
 * (its options and the acting user), and which 1-based row number this is — useful
 * for provenance and for row-level error messages.
 */
final readonly class ImportRowContext
{
    public function __construct(
        public ImportSession $session,
        public int $rowNumber,
    ) {}

    /**
     * The source key an importer should stamp on its {@see RecordOriginRegistry}
     * entries for rows of this session, so re-importing the same file dedupes.
     */
    public function sourceKey(): string
    {
        return 'import:' . $this->session->entity_key;
    }
}
