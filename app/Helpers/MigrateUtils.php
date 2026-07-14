<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\Concerns\HasValidity;

final class MigrateUtils
{
    /**
     * Add a portable B-tree index intended for exact and prefix matching.
     *
     * @param  string|list<string>  $columns
     */
    public static function prefixIndex(Blueprint $table, string|array $columns, ?string $name = null): void
    {
        $normalized_columns = self::normalizeColumns($columns);
        $index_name = $name ?? self::searchIndexName($table->getTable(), $normalized_columns, 'prefix');
        self::assertIdentifier($index_name);

        $table->index($normalized_columns, $index_name);
    }

    public static function fuzzyIndex(string $table, string $column, ?string $name = null, string $oracleSync = 'on_commit'): void
    {
        self::assertIdentifier($table);
        self::assertIdentifier($column);
        self::assertOracleSync($oracleSync);
        $index_name = $name ?? self::searchIndexName($table, [$column], 'fuzzy');
        self::assertIdentifier($index_name);

        match (DB::connection()->getDriverName()) {
            'pgsql' => self::createPostgresFuzzyIndex($table, $column, $index_name),
            'oracle' => self::createOracleContextIndex($table, $column, $index_name, $oracleSync),
            default => null,
        };
    }

    /**
     * @param  string|list<string>  $columns
     */
    public static function fullTextIndex(
        string $table,
        string|array $columns,
        string $language = 'simple',
        ?string $name = null,
        string $oracleSync = 'manual',
    ): void {
        self::assertIdentifier($table);
        self::assertIdentifier($language);
        self::assertOracleSync($oracleSync);
        $normalized_columns = self::normalizeColumns($columns);
        $index_name = $name ?? self::searchIndexName($table, $normalized_columns, 'fulltext');
        self::assertIdentifier($index_name);

        match (DB::connection()->getDriverName()) {
            'pgsql' => self::createPostgresFullTextIndex($table, $normalized_columns, $language, $index_name),
            'mysql', 'mariadb' => self::createMysqlFullTextIndex($table, $normalized_columns, $index_name),
            'oracle' => count($normalized_columns) === 1
                ? self::createOracleContextIndex($table, $normalized_columns[0], $index_name, $oracleSync)
                : throw new InvalidArgumentException('Oracle CONTEXT indexes require one normalized search-text column.'),
            default => null,
        };
    }

    public static function dropFuzzyIndex(string $table, string $column, ?string $name = null): void
    {
        self::dropSpecializedSearchIndex($table, $name ?? self::searchIndexName($table, [$column], 'fuzzy'));
    }

    /**
     * @param  string|list<string>  $columns
     */
    public static function dropFullTextIndex(string $table, string|array $columns, ?string $name = null): void
    {
        $normalized_columns = self::normalizeColumns($columns);
        self::dropSpecializedSearchIndex($table, $name ?? self::searchIndexName($table, $normalized_columns, 'fulltext'));
    }

    /**
     * Add common timestamp columns to a table.
     *
     * @param  Blueprint  $table  The table blueprint
     * @param  bool  $hasCreateUpdate  Add created_at and updated_at columns
     * @param  bool  $hasSoftDelete  Add soft delete functionality
     * @param  bool  $hasLocks  Add locking columns
     * @param  bool  $hasValidity  Add validity period columns
     * @param  bool  $isValidityRequired  Make valid_from required
     *
     * @throws InvalidArgumentException When invalid parameter combination
     */
    public static function timestamps(
        Blueprint $table,
        bool $hasCreateUpdate = true,
        bool $hasSoftDelete = false,
        bool $hasLocks = false,
        bool $hasValidity = false,
        bool $isValidityRequired = true,
    ): void {
        $table_name = $table->getTable();

        if ($hasCreateUpdate) {
            if (! Schema::hasColumn($table_name, Model::CREATED_AT)) {
                $table->timestamp(Model::CREATED_AT)->nullable(false)->useCurrent();
            }

            if (! Schema::hasColumn($table_name, Model::UPDATED_AT)) {
                $table->timestamp(Model::UPDATED_AT)->nullable(false)->useCurrent()->useCurrentOnUpdate();
            }

            self::createDateIndex($table, Model::CREATED_AT);
        }

        if ($hasSoftDelete) {
            self::softDeletes($table);
        }

        if ($hasLocks) {
            self::locked($table);
        }

        if ($hasValidity) {
            $valid_from_column = HasValidity::validFromKey();
            $valid_to_column = HasValidity::validToKey();

            if (! Schema::hasColumn($table_name, $valid_from_column)) {
                if ($isValidityRequired) {
                    $table->datetime($valid_from_column)->nullable(false)->useCurrent();
                } else {
                    $table->datetime($valid_from_column)->nullable(true);
                }
            }

            if (! Schema::hasColumn($table_name, $valid_to_column)) {
                $table->datetime($valid_to_column)->nullable(true);
            }

            self::createDateIndex($table, $valid_from_column);

            self::createDateIndex($table, $valid_to_column);

            $index_name = $table_name . '_validity_idx';

            if (! Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column]) && ! Schema::hasIndex($table_name, $index_name)) {
                DB::afterCommit(function () use ($table, $table_name, $valid_from_column, $valid_to_column, $index_name): void {
                    match (DB::connection()->getDriverName()) {
                        'pgsql' => DB::statement(sprintf('CREATE INDEX %s ON %s (%s DESC, %s)', $index_name, $table_name, $valid_from_column, $valid_to_column)),
                        default => $table->index([$valid_from_column, $valid_to_column], $index_name),
                    };
                });
            }
        }

        $index_name = $table_name . '_validity_deleted_idx';

        if ($hasSoftDelete && $hasValidity && ! Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column, 'is_deleted']) && ! Schema::hasIndex($table_name, $index_name)) {
            DB::afterCommit(function () use ($table, $table_name, $valid_from_column, $valid_to_column, $index_name): void {
                match (DB::connection()->getDriverName()) {
                    'pgsql' => DB::statement(sprintf('CREATE INDEX %s ON %s (%s DESC, %s, is_deleted)', $index_name, $table_name, $valid_from_column, $valid_to_column)),
                    default => $table->index([$valid_from_column, $valid_to_column, 'is_deleted'], $index_name),
                };
            });
        }
    }

    /**
     * Drop common timestamp columns from a table.
     *
     * @param  Blueprint  $table  The table blueprint
     * @param  bool  $hasCreateUpdate  Drop created_at and updated_at columns
     * @param  bool  $hasSoftDelete  Drop soft delete functionality
     * @param  bool  $hasLocks  Drop locking columns
     * @param  bool  $hasValidity  Drop validity period columns
     */
    public static function dropTimestamps(
        Blueprint $table,
        bool $hasCreateUpdate = true,
        bool $hasSoftDelete = false,
        bool $hasLocks = false,
        bool $hasValidity = false,
    ): void {
        $table_name = $table->getTable();

        if ($hasCreateUpdate) {
            $index_name = $table_name . '_created_at_idx';

            if (Schema::hasIndex($table_name, [Model::CREATED_AT]) || Schema::hasIndex($table_name, $index_name)) {
                $table->dropIndex($index_name);
            }

            if (Schema::hasColumn($table_name, Model::CREATED_AT)) {
                $table->dropColumn(Model::CREATED_AT);
            }

            if (Schema::hasColumn($table_name, Model::UPDATED_AT)) {
                $table->dropColumn(Model::UPDATED_AT);
            }
        }

        if ($hasSoftDelete) {
            self::dropSoftDeletes($table);
        }

        if ($hasLocks) {
            self::dropLocked($table);
        }

        if ($hasValidity) {
            $valid_from_column = HasValidity::validFromKey();
            $valid_to_column = HasValidity::validToKey();
            $index_name = $table_name . '.validity_range';

            if (Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column]) || Schema::hasIndex($table_name, $index_name)) {
                $table->dropIndex($index_name);
            }

            if (Schema::hasColumn($table_name, $valid_from_column)) {
                $table->dropColumn($valid_from_column);
            }

            if (Schema::hasColumn($table_name, $valid_to_column)) {
                $table->dropColumn($valid_to_column);
            }
        }
    }

    private static function softDeletes(Blueprint $table): void
    {
        if (! Schema::hasColumn($table->getTable(), 'deleted_at')) {
            $table->softDeletes();
        }

        if (! Schema::hasColumn($table->getTable(), 'is_deleted')) {
            switch (DB::connection()->getDriverName()) {
                case 'pgsql':
                    $table->boolean('is_deleted')->storedAs('deleted_at IS NOT NULL')->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');

                    break;
                case 'oracle':
                    // Oracle richiede ancora i trigger
                    $table->boolean('is_deleted')->default(false)->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');
                    DB::afterCommit(function () use ($table): void {
                        self::createBooleanTriggers($table, 'deleted');
                    });

                    break;
                default:
                    // MySQL supporta generated columns
                    $table->boolean('is_deleted')->storedAs('IF(deleted_at IS NULL, 0, 1)')->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');

                    break;
            }
        }
    }

    private static function dropSoftDeletes(Blueprint $table): void
    {
        // Rimuoviamo i trigger solo per Oracle
        if (DB::connection()->getDriverName() === 'oracle') {
            self::dropBooleanTriggers($table, 'deleted');
        }

        if (Schema::hasColumn($table->getTable(), 'deleted_at')) {
            $table->dropSoftDeletes();
        }

        if (Schema::hasColumn($table->getTable(), 'is_deleted')) {
            $table->dropColumn('is_deleted');
        }
    }

    private static function locked(Blueprint $table): void
    {
        $locked = new Locked();
        $locked_at_column = $locked->lockedAtColumn();

        if ($locked_at_column !== '' && $locked_at_column !== '0' && ! Schema::hasColumn($table->getTable(), $locked_at_column)) {
            $table->timestamp($locked_at_column)->nullable()->comment('The date and time when the entity was locked');
        }

        $locked_by_column = $locked->lockedByColumn();

        if ($locked_by_column !== '' && $locked_by_column !== '0' && ! Schema::hasColumn($table->getTable(), $locked_by_column)) {
            $table->timestamp($locked_by_column)->nullable()->comment('The user who locked the entity');
        }

        if (! Schema::hasColumn($table->getTable(), 'is_locked')) {
            switch (DB::connection()->getDriverName()) {
                case 'pgsql':
                    $table->boolean('is_locked')->storedAs($locked_at_column . ' IS NOT NULL')->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');

                    break;
                case 'oracle':
                    // Oracle richiede ancora i trigger
                    $table->boolean('is_locked')->default(false)->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');
                    DB::afterCommit(function () use ($table): void {
                        self::createBooleanTriggers($table, 'locked');
                    });

                    break;
                default:
                    // MySQL supporta generated columns
                    $table->boolean('is_locked')->storedAs('IF(' . $locked_at_column . ' IS NULL, 0, 1)')->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');

                    break;
            }
        }
    }

    private static function dropLocked(Blueprint $table): void
    {
        // Rimuoviamo i trigger solo per Oracle
        if (DB::connection()->getDriverName() === 'oracle') {
            self::dropBooleanTriggers($table, 'locked');
        }

        $locked = new Locked();
        $locked_at_column = $locked->lockedAtColumn();

        if ($locked_at_column !== '' && $locked_at_column !== '0' && Schema::hasColumn($table->getTable(), $locked_at_column)) {
            $table->dropColumn($locked_at_column);
        }

        $locked_by_column = $locked->lockedByColumn();

        if ($locked_by_column !== '' && $locked_by_column !== '0' && Schema::hasColumn($table->getTable(), $locked_by_column)) {
            $table->dropColumn($locked_by_column);
        }

        if (Schema::hasColumn($table->getTable(), 'is_locked')) {
            $table->dropColumn('is_locked');
        }
    }

    private static function createBooleanTriggers(Blueprint $table, string $suffix): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            // In Oracle we use a virtual column with a check constraint
            // Create trigger for Oracle
            DB::unprepared('
                CREATE OR REPLACE TRIGGER ' . $table->getTable() . '_is_' . $suffix . '_trigger
                BEFORE INSERT OR UPDATE ON ' . $table->getTable() . '
                FOR EACH ROW
                BEGIN
                    :NEW.is_' . $suffix . ' := CASE 
                        WHEN :NEW.' . $suffix . '_at IS NOT NULL THEN 1 
                        ELSE 0 
                    END;
                END;
            ');
        }
    }

    private static function dropBooleanTriggers(Blueprint $table, string $suffix): void
    {
        if (DB::connection()->getDriverName() === 'oracle') {
            DB::unprepared('
                BEGIN
                    EXECUTE IMMEDIATE \'DROP TRIGGER ' . $table->getTable() . '_is_' . $suffix . '_trigger\';
                EXCEPTION
                    WHEN OTHERS THEN
                        IF SQLCODE != -4080 THEN  -- ORA-04080: trigger does not exist
                            RAISE;
                        END IF;
                END;
            ');
        }
    }

    private static function createDateIndex(Blueprint $table, string $column): void
    {
        $index_name = $table->getTable() . '_' . $column . '_idx';
        $driver_name = DB::connection()->getDriverName();

        if (Schema::hasIndex($table->getTable(), $column) || Schema::hasIndex($table->getTable(), $index_name)) {
            return;
        }

        if ($driver_name === 'pgsql') {
            DB::afterCommit(function () use ($table, $column, $index_name): void {
                DB::statement('CREATE INDEX ' . $index_name . ' ON ' . $table->getTable() . ' USING BRIN (' . $column . ')');
            });

            return;
        }

        // MySQL and Oracle are not able to handle DESC indexes through Blueprint; to avoid
        // race conditions during table creation we use a standard index created along with the table.
        $table->index($column, $index_name);
    }

    /**
     * @param  string|list<string>  $columns
     * @return list<string>
     */
    private static function normalizeColumns(string|array $columns): array
    {
        $normalized = is_string($columns) ? [$columns] : array_values($columns);

        throw_if($normalized === [], InvalidArgumentException::class, 'At least one search index column is required.');

        foreach ($normalized as $column) {
            throw_unless(is_string($column), InvalidArgumentException::class, 'Search index columns must be strings.');
            self::assertIdentifier($column);
        }

        return $normalized;
    }

    private static function assertIdentifier(string $identifier): void
    {
        throw_unless(
            preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1,
            InvalidArgumentException::class,
            sprintf('Invalid database identifier [%s].', $identifier),
        );
    }

    private static function assertOracleSync(string $sync): void
    {
        throw_unless(
            in_array($sync, ['manual', 'on_commit'], true),
            InvalidArgumentException::class,
            'Oracle Text sync must be [manual] or [on_commit].',
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private static function searchIndexName(string $table, array $columns, string $suffix): string
    {
        self::assertIdentifier($table);
        $base = mb_strtolower(implode('_', [$table, ...$columns, $suffix, 'idx']));
        $limit = match (DB::connection()->getDriverName()) {
            'oracle' => 30,
            'pgsql' => 63,
            default => 64,
        };

        if (mb_strlen($base) <= $limit) {
            return $base;
        }

        $hash = mb_substr(sha1($base), 0, 8);

        return mb_substr($base, 0, $limit - 9) . '_' . $hash;
    }

    private static function createPostgresFuzzyIndex(string $table, string $column, string $indexName): void
    {
        DB::unprepared('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(sprintf('CREATE INDEX %s ON %s USING GIN (%s gin_trgm_ops)', $indexName, $table, $column));
    }

    /**
     * @param  list<string>  $columns
     */
    private static function createPostgresFullTextIndex(string $table, array $columns, string $language, string $indexName): void
    {
        $document = implode(" || ' ' || ", array_map(
            static fn (string $column): string => sprintf("coalesce(%s, '')", $column),
            $columns,
        ));

        DB::statement(sprintf(
            "CREATE INDEX %s ON %s USING GIN (to_tsvector('%s', %s))",
            $indexName,
            $table,
            $language,
            $document,
        ));
    }

    /**
     * @param  list<string>  $columns
     */
    private static function createMysqlFullTextIndex(string $table, array $columns, string $indexName): void
    {
        DB::statement(sprintf(
            'ALTER TABLE %s ADD FULLTEXT INDEX %s (%s)',
            $table,
            $indexName,
            implode(', ', $columns),
        ));
    }

    private static function createOracleContextIndex(string $table, string $column, string $indexName, string $sync): void
    {
        $parameters = $sync === 'on_commit' ? " PARAMETERS ('SYNC (ON COMMIT)')" : '';

        DB::statement(sprintf(
            'CREATE INDEX %s ON %s (%s) INDEXTYPE IS CTXSYS.CONTEXT%s',
            $indexName,
            $table,
            $column,
            $parameters,
        ));
    }

    private static function dropSpecializedSearchIndex(string $table, string $indexName): void
    {
        self::assertIdentifier($table);
        self::assertIdentifier($indexName);

        match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => DB::statement(sprintf('ALTER TABLE %s DROP INDEX %s', $table, $indexName)),
            'pgsql', 'oracle' => DB::statement(sprintf('DROP INDEX %s', $indexName)),
            default => null,
        };
    }
}
