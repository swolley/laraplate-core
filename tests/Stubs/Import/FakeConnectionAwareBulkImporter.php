<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Import\Contracts\BulkImporterInterface;
use Modules\Core\Import\Contracts\ConnectionAwareBulkImporterInterface;

final readonly class FakeConnectionAwareBulkImporter implements BulkImporterInterface, ConnectionAwareBulkImporterInterface
{
    public function __construct(
        private DatabaseManager $database,
        private string $connectionName,
        private string $table,
    ) {}

    public function import(): int
    {
        $this->importConnection()->table($this->table)->insert(['name' => 'connection-aware']);

        return 1;
    }

    public function importConnection(): ConnectionInterface
    {
        return $this->database->connection($this->connectionName);
    }
}
