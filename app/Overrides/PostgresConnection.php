<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use Override;
use PDO;

/**
 * Postgres connection that can run with emulated prepares (`DB_EMULATE_PREPARES`):
 * one round trip per query instead of a prepare plus an execute, which halves the
 * cost of every query against a remote server.
 *
 * The framework binds booleans as integers. Native prepares let Postgres type the
 * parameter from the column, but an emulated prepare writes the value into the SQL
 * as a literal and `boolean = integer` has no operator, so booleans are bound as
 * `'true'` / `'false'` instead while emulation is on.
 */
final class PostgresConnection extends BasePostgresConnection
{
    /**
     * Whether a connection's PDO options turn emulated prepares on.
     */
    public static function emulatesPreparesIn(mixed $options): bool
    {
        return is_array($options) && ($options[PDO::ATTR_EMULATE_PREPARES] ?? false) === true;
    }

    /**
     * @param  array<array-key, mixed>  $bindings
     * @return array<array-key, mixed>
     */
    #[Override]
    public function prepareBindings(array $bindings): array
    {
        if (self::emulatesPreparesIn($this->getConfig('options'))) {
            foreach ($bindings as $key => $value) {
                if (is_bool($value)) {
                    $bindings[$key] = $value ? 'true' : 'false';
                }
            }
        }

        return parent::prepareBindings($bindings);
    }
}
