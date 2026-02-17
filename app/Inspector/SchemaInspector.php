<?php

declare(strict_types=1);

namespace Modules\Core\Inspector;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\Entities\Table;

/**
 * Singleton that provides in-request memoization for schema inspection.
 * All table/columns/indexes/foreignKeys lookups for the same table/connection
 * hit the same in-memory entry, reducing cache and DB access.
 */
final class SchemaInspector
{
    private static ?self $instance = null;

    /**
     * In-memory cache of inspected tables. Key: Inspect::keyName($table, $connection).
     *
     * @var array<string, Table>
     */
    private array $tables = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Retrieve a particular table for a given database connection.
     * Results are memoized in-memory for the request/command lifecycle.
     */
    public function table(string $name, ?string $connection = null): ?Table
    {
        $key = Inspect::keyName($name, $connection);

        if (isset($this->tables[$key])) {
            return $this->tables[$key];
        }

        $inspected = Inspect::table($name, $connection);

        if ($inspected instanceof Table) {
            $this->tables[$key] = $inspected;
        }

        return $inspected;
    }

    /**
     * Retrieve the columns of a particular table for a given database connection.
     *
     * @return Collection<int, Column>
     */
    public function columns(string $table, ?string $connection = null): Collection
    {
        $inspected = $this->table($table, $connection);

        return $inspected instanceof Table ? $inspected->columns : new Collection();
    }

    /**
     * Retrieve the indexes of a particular table for a given database connection.
     *
     * @return Collection<int, Index>
     */
    public function indexes(string $table, ?string $connection = null): Collection
    {
        $inspected = $this->table($table, $connection);

        return $inspected instanceof Table ? $inspected->indexes : new Collection();
    }

    /**
     * Retrieve the foreign keys of a particular table for a given database connection.
     *
     * @return Collection<int, ForeignKey>
     */
    public function foreignKeys(string $table, ?string $connection = null): Collection
    {
        $inspected = $this->table($table, $connection);

        return $inspected instanceof Table ? $inspected->foreignKeys : new Collection();
    }

    /**
     * Retrieve a particular column of a particular table for a given database connection.
     */
    public function column(string $name, string $table, ?string $connection = null): ?Column
    {
        $columns = $this->columns($table, $connection);

        return Arr::first($columns, fn (Column $column): bool => $column->name === $name);
    }

    /**
     * Retrieve a particular index of a particular table for a given database connection.
     */
    public function index(string $name, string $table, ?string $connection = null): ?Index
    {
        $indexes = $this->indexes($table, $connection);

        return Arr::first($indexes, fn (Index $index): bool => $index->name === $name);
    }

    /**
     * Retrieve a particular foreign key of a particular table for a given database connection.
     */
    public function foreignKey(string $name, string $table, ?string $connection = null): ?ForeignKey
    {
        $foreignKeys = $this->foreignKeys($table, $connection);

        return Arr::first($foreignKeys, fn (ForeignKey $fk): bool => $fk->name === $name);
    }

    /**
     * Clear in-memory cache for a single table. Tag cache is not modified.
     */
    public function clearTable(string $table, ?string $connection = null): void
    {
        $key = Inspect::keyName($table, $connection);
        unset($this->tables[$key]);
    }

    /**
     * Clear all in-memory cached tables. Tag cache is not modified.
     */
    public function clearAll(): void
    {
        $this->tables = [];
    }
}
