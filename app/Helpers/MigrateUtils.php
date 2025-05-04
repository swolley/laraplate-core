<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Modules\Core\Locking\Locked;

class MigrateUtils
{
    /**
     * Add common timestamp columns to a table
     *
     * @param Blueprint $table The table blueprint
     * @param bool $hasCreateUpdate Add created_at and updated_at columns
     * @param bool $hasSoftDelete Add soft delete functionality
     * @param bool $hasLocks Add locking columns
     * @param bool $hasValidity Add validity period columns
     * @param bool $isValidityRequired Make valid_from required
     * @param bool $addIndices Add performance indices
     * @throws \InvalidArgumentException When invalid parameter combination
     */
    public static function timestamps(
        Blueprint $table,
        bool $hasCreateUpdate = true,
        bool $hasSoftDelete = false,
        bool $hasLocks = false,
        bool $hasValidity = false,
        bool $isValidityRequired = true
    ): void {
        $table_name = $table->getTable();
        if ($hasCreateUpdate) {
            if (!Schema::hasColumn($table_name, Model::CREATED_AT)) {
                $table->timestamp(Model::CREATED_AT)->nullable(false)->useCurrent();
            }
            if (!Schema::hasColumn($table_name, Model::UPDATED_AT)) {
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
            if (!Schema::hasColumn($table_name, $valid_from_column)) {
                if ($isValidityRequired) {
                    $table->datetime($valid_from_column)->nullable(false)->useCurrent();
                } else {
                    $table->datetime($valid_from_column)->nullable(true);
                }
            }
            if (!Schema::hasColumn($table_name, $valid_to_column)) {
                $table->datetime($valid_to_column)->nullable(true);
            }

            self::createDateIndex($table, $valid_from_column);

            self::createDateIndex($table, $valid_to_column);

            $index_name = $table_name . '_validity_idx';
            if (!Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column]) && !Schema::hasIndex($table_name, $index_name)) {
                DB::afterCommit(function () use ($table, $table_name, $valid_from_column, $valid_to_column, $index_name) {
                    switch (DB::connection()->getDriverName()) {
                        case 'pgsql':
                            DB::statement("CREATE INDEX {$index_name} ON {$table_name} ({$valid_from_column} DESC, {$valid_to_column})");
                            break;
                        default:
                            $table->index([$valid_from_column, $valid_to_column], $index_name);
                            break;
                    }
                });
            }
        }

        $index_name = $table_name . '_validity_deleted_idx';
        if ($hasSoftDelete && $hasValidity && !Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column, 'is_deleted']) && !Schema::hasIndex($table_name, $index_name)) {
            DB::afterCommit(function () use ($table, $table_name, $valid_from_column, $valid_to_column, $index_name) {
                switch (DB::connection()->getDriverName()) {
                    case 'pgsql':
                        DB::statement("CREATE INDEX {$index_name} ON {$table_name} ({$valid_from_column} DESC, {$valid_to_column}, is_deleted)");
                        break;
                    default:
                        $table->index([$valid_from_column, $valid_to_column, 'is_deleted'], $index_name);
                        break;
                }
            });
        }
    }

    /**
     * Drop common timestamp columns from a table
     *
     * @param Blueprint $table The table blueprint
     * @param bool $hasCreateUpdate Drop created_at and updated_at columns
     * @param bool $hasSoftDelete Drop soft delete functionality
     * @param bool $hasLocks Drop locking columns
     * @param bool $hasValidity Drop validity period columns
     */
    public static function dropTimestamps(
        Blueprint $table,
        bool $hasCreateUpdate = true,
        bool $hasSoftDelete = false,
        bool $hasLocks = false,
        bool $hasValidity = false
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
            $index_name = "{$table_name}.validity_range";
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
        if (!Schema::hasColumn($table->getTable(), 'deleted_at')) {
            $table->softDeletes();
        }

        if (!Schema::hasColumn($table->getTable(), 'is_deleted')) {
            switch (DB::connection()->getDriverName()) {
                case 'pgsql':
                    $table->boolean('is_deleted')->storedAs('deleted_at IS NOT NULL')->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');
                    break;
                case 'oracle':
                    // Oracle richiede ancora i trigger
                    $table->boolean('is_deleted')->default(false)->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');
                    DB::afterCommit(function () use ($table) {
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
        if ($locked_at_column) {
            if (!Schema::hasColumn($table->getTable(), $locked_at_column)) {
                $table->timestamp($locked_at_column)->nullable()->comment('The date and time when the entity was locked');
            }
        }
        $locked_by_column = $locked->lockedByColumn();
        if ($locked_by_column) {
            if (!Schema::hasColumn($table->getTable(), $locked_by_column)) {
                $table->timestamp($locked_by_column)->nullable()->comment('The user who locked the entity');
            }
        }

        if (!Schema::hasColumn($table->getTable(), 'is_locked')) {
            switch (DB::connection()->getDriverName()) {
                case 'pgsql':
                    $table->boolean('is_locked')->storedAs($locked_at_column . ' IS NOT NULL')->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');
                    break;
                case 'oracle':
                    // Oracle richiede ancora i trigger
                    $table->boolean('is_locked')->default(false)->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');
                    DB::afterCommit(function () use ($table) {
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
        if ($locked_at_column) {
            if (Schema::hasColumn($table->getTable(), $locked_at_column)) {
                $table->dropColumn($locked_at_column);
            }
        }
        $locked_by_column = $locked->lockedByColumn();
        if ($locked_by_column) {
            if (Schema::hasColumn($table->getTable(), $locked_by_column)) {
                $table->dropColumn($locked_by_column);
            }
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
        if (!Schema::hasIndex($table->getTable(), $column) && !Schema::hasIndex($table->getTable(), $index_name)) {
            DB::afterCommit(function () use ($table, $column, $index_name) {
                switch (DB::connection()->getDriverName()) {
                    case 'pgsql':
                        DB::statement('CREATE INDEX ' . $index_name . ' ON ' . $table->getTable() . ' USING BRIN (' . $column . ')');
                        break;
                    case 'oracle':
                        DB::statement('CREATE INDEX ' . $index_name . ' ON ' . $table->getTable() . ' (' . $column . ' DESC)');
                        break;
                    case 'mysql':
                        DB::statement('CREATE INDEX ' . $index_name . ' ON ' . $table->getTable() . ' (' . $column . ' DESC)');
                        break;
                }
            });
        }
    }
}
