<?php

declare(strict_types=1);

namespace Modules\Core\Import\Contracts;

use Illuminate\Database\ConnectionInterface;

interface ConnectionAwareBulkImporterInterface
{
    public function importConnection(): ConnectionInterface;
}
