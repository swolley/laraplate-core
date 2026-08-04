<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

interface BulkImporterInterface
{
    /**
     * Run the configured import.
     *
     * @return int Number of imported root records.
     */
    public function import(?OutputInterface $output = null): int;
}
