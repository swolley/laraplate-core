<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;

/**
 * Moves the `updated_at` of a record whose own row did not change: a translation or a relation of it did. The
 * incremental search sync and the freshness fingerprint of the lists read that column as the signal that
 * something changed (decisions D30 and D37 of the revision-centric history review).
 *
 * A direct UPDATE of that column alone: no model event, no `lock_version`, no version row. Inside a transaction
 * an owner is touched once (a `sync()` of ten tags runs one UPDATE); the owners touched are remembered per
 * transaction level and forgotten when that level rolls back, a savepoint included, so a change after a
 * rollback is never skipped (D51). Outside a transaction every touch is written.
 *
 * Bound as a scoped instance: it lives as long as the request or the job.
 */
final class OwnerTimestamp
{
    /**
     * Rows touched in the open transactions, per connection and per transaction level.
     *
     * @var array<string, array<int, array<string, true>>>
     */
    private array $touched = [];

    public static function touch(Model $owner, int|string $key): void
    {
        if (! $owner->usesTimestamps() || $owner->getUpdatedAtColumn() === null) {
            return;
        }

        $connection = $owner->getConnection();

        app(self::class)->touchRow($connection, $owner->getTable(), $owner->getKeyName(), $key, $owner->getUpdatedAtColumn(), $owner->freshTimestampString());
    }

    public function touchRow(Connection $connection, string $table, string $keyName, int|string $key, string $column, string $now): void
    {
        $name = (string) $connection->getName();
        $level = $connection->transactionLevel();
        $row = $table . '|' . $key;

        if ($level > 0) {
            foreach ($this->touched[$name] ?? [] as $touched_level => $rows) {
                if ($touched_level <= $level && isset($rows[$row])) {
                    return;
                }
            }

            $this->touched[$name][$level][$row] = true;
        }

        $connection->table($table)->where($keyName, $key)->update([$column => $now]);
    }

    /**
     * A level was committed: what it touched now belongs to the level that contains it, or is written for good.
     */
    public function committed(Connection $connection): void
    {
        $name = (string) $connection->getName();
        $level = $connection->transactionLevel();
        $closed = [];

        foreach ($this->touched[$name] ?? [] as $touched_level => $rows) {
            if ($touched_level > $level) {
                $closed += $rows;
                unset($this->touched[$name][$touched_level]);
            }
        }

        if ($level > 0 && $closed !== []) {
            $this->touched[$name][$level] = ($this->touched[$name][$level] ?? []) + $closed;
        }

        $this->forgetEmpty($name);
    }

    /**
     * A level was rolled back, a savepoint or the whole transaction: what it touched was undone.
     */
    public function rolledBack(Connection $connection): void
    {
        $name = (string) $connection->getName();
        $level = $connection->transactionLevel();

        foreach (array_keys($this->touched[$name] ?? []) as $touched_level) {
            if ($touched_level > $level) {
                unset($this->touched[$name][$touched_level]);
            }
        }

        $this->forgetEmpty($name);
    }

    private function forgetEmpty(string $name): void
    {
        if (($this->touched[$name] ?? []) === []) {
            unset($this->touched[$name]);
        }
    }
}
