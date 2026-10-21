<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Exceptions\AggregateException;

/**
 * The one driver-specific piece of SQL of the aggregate: "truncate a timestamp to its day".
 *
 * A time bucket is cut by grouping on this expression and rolling the days up in PHP, so no other function
 * of the engine depends on the database. Only the sqlite expression runs in CI; the others are checked as
 * strings and against a real server at release.
 */
final class DayTruncation
{
    /**
     * The expression that gives the day of a column, for the driver of the connection.
     *
     * @param  string  $driver  The name of the connection driver, as `Connection::getDriverName()` gives it.
     * @param  string  $wrappedColumn  A column already wrapped by the grammar of the connection, never request text.
     *
     * @throws AggregateException When the driver has no expression: a fault of the deployment, so it is reported.
     */
    public static function expression(string $driver, string $wrappedColumn): string
    {
        return match ($driver) {
            'sqlite' => 'date(' . $wrappedColumn . ')',
            'mysql', 'mariadb' => 'DATE(' . $wrappedColumn . ')',
            'pgsql', 'sqlsrv' => 'CAST(' . $wrappedColumn . ' AS date)',
            'oracle' => 'TRUNC(' . $wrappedColumn . ')',
            default => throw new AggregateException(
                AggregateErrorCode::UnsupportedFunction,
                sprintf('Time buckets are not supported on the "%s" database driver.', $driver),
                'group_by.bucket',
                reportable: true,
            ),
        };
    }
}
