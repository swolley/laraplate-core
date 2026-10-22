<?php

declare(strict_types=1);

namespace Modules\Core\Versioning;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionRecord;

/**
 * Whether the application opened a database transaction on a connection. The one `RefreshDatabase` wraps around
 * each test does not count: Laravel's transaction manager leaves it out of the transactions after-commit callbacks
 * apply to, and so does this, or every version set of the suite would look atomic (D52).
 */
final class ApplicationTransactions
{
    public static function isOpenOn(Connection $connection): bool
    {
        $name = $connection->getName();

        return app('db.transactions')->callbackApplicableTransactions()
            ->contains(static fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection === $name);
    }
}
