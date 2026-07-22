<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

final readonly class BulkImportRunner
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    public static function limitReached(int $imported, ?int $limit): bool
    {
        return $limit !== null && $limit > 0 && $imported >= $limit;
    }

    /**
     * @param  callable(): int  $import
     */
    public function run(bool $dryRun, callable $import): int
    {
        if (! $dryRun) {
            return $import();
        }

        $connection = $this->database->connection();
        $initial_level = $connection->transactionLevel();
        $connection->beginTransaction();

        try {
            return $import();
        } finally {
            $this->rollbackToLevel($connection, $initial_level);
        }
    }

    private function rollbackToLevel(ConnectionInterface $connection, int $initial_level): void
    {
        while ($connection->transactionLevel() > $initial_level) {
            $connection->rollBack();
        }
    }
}
