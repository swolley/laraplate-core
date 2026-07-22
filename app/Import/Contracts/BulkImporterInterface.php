<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

interface BulkImporterInterface
{
    /**
     * Run the configured import.
     *
     * @return int Number of imported root records.
     */
    public function import(): int;
}
