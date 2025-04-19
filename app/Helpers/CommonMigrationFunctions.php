<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Modules\Core\Locking\Locked;

class CommonMigrationFunctions
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

            if (!Schema::hasIndex($table_name, [Model::CREATED_AT])) {
                $table->index([Model::CREATED_AT], $table_name . '_created_at_idx');
            }
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

            if (!Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column])) {
                $table->index([$valid_from_column, $valid_to_column], $table_name . '_validity_range');
            }
        }
    }

    /**
     * Drop common timestamp columns from a table
     *
     * @param Blueprint $table The table blueprint
     * @param bool $hasCreateUpdate Add created_at and updated_at columns
     * @param bool $hasSoftDelete Add soft delete functionality
     * @param bool $hasLocks Add locking columns
     * @param bool $hasValidity Add validity period columns
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
            if (Schema::hasIndex($table_name, [Model::CREATED_AT])) {
                $table->dropIndex($table_name . '_created_at_idx');
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
            if (Schema::hasIndex($table_name, [$valid_from_column, $valid_to_column])) {
                $table->dropIndex($table_name . '_validity_range');
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
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->boolean('is_deleted')->storedAs('deleted_at IS NOT NULL')->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');
            } elseif (DB::connection()->getDriverName() === 'oracle') {
                // Oracle richiede ancora i trigger
                $table->boolean('is_deleted')->default(false)->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');
                DB::afterCommit(function () use ($table) {
                    self::createBooleanTriggers($table, 'deleted');
                });
            } else {
                // MySQL supporta generated columns
                $table->boolean('is_deleted')->storedAs('IF(deleted_at IS NULL, 0, 1)')->index($table->getTable() . '_is_deleted_idx')->comment('Whether the entity is deleted');
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
        if ($locked_at_column = $locked->lockedAtColumn()) {
            if (!Schema::hasColumn($table->getTable(), $locked_at_column)) {
                $table->timestamp($locked_at_column)->nullable()->comment('The date and time when the entity was locked');
            }
        }
        if ($locked_by_column = $locked->lockedByColumn()) {
            if (!Schema::hasColumn($table->getTable(), $locked_by_column)) {
                $table->timestamp($locked_by_column)->nullable()->comment('The user who locked the entity');
            }
        }

        if (!Schema::hasColumn($table->getTable(), 'is_locked')) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->boolean('is_locked')->storedAs($locked_at_column . ' IS NOT NULL')->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');
            } elseif (DB::connection()->getDriverName() === 'oracle') {
                // Oracle richiede ancora i trigger
                $table->boolean('is_locked')->default(false)->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');
                DB::afterCommit(function () use ($table) {
                    self::createBooleanTriggers($table, 'locked');
                });
            } else {
                // MySQL supporta generated columns
                $table->boolean('is_locked')->storedAs('IF(' . $locked_at_column . ' IS NULL, 0, 1)')->index($table->getTable() . '_is_locked_idx')->comment('Whether the entity is locked');
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
        if ($locked_at_column = $locked->lockedAtColumn()) {
            if (Schema::hasColumn($table->getTable(), $locked_at_column)) {
                $table->dropColumn($locked_at_column);
            }
        }
        if ($locked_by_column = $locked->lockedByColumn()) {
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
        /* if (DB::connection()->getDriverName() === 'pgsql') {
            // Create trigger function
            DB::unprepared('
                DO $$ 
                BEGIN
                    IF NOT EXISTS (SELECT 1 FROM pg_proc WHERE proname = \'update_is_' . $suffix . '\') THEN
                        CREATE FUNCTION update_is_' . $suffix . '()
                        RETURNS TRIGGER AS $func$
                        BEGIN
                            NEW.is_' . $suffix . ' = CASE 
                                WHEN NEW.' . $suffix . '_at IS NOT NULL THEN true 
                                ELSE false 
                            END;
                            RETURN NEW;
                        END;
                        $func$ LANGUAGE plpgsql;
                    END IF;
                END $$;
            ');

            // Create trigger
            DB::unprepared('
                DO $$ 
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_trigger 
                        WHERE tgname = \'' . $table->getTable() . '_is_' . $suffix . '_trigger\'
                    ) THEN
                        CREATE TRIGGER ' . $table->getTable() . '_is_' . $suffix . '_trigger
                        BEFORE INSERT OR UPDATE ON ' . $table->getTable() . '
                        FOR EACH ROW
                        EXECUTE FUNCTION update_is_' . $suffix . '();
                    END IF;
                END $$;
            ');
        } else*/
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
        } /*else {
            // Create trigger for MySQL
            DB::unprepared('
                CREATE TRIGGER IF NOT EXISTS ' . $table->getTable() . '_is_' . $suffix . '_trigger
                BEFORE INSERT ON ' . $table->getTable() . '
                FOR EACH ROW
                SET NEW.is_' . $suffix . ' = IF(NEW.' . $suffix . '_at IS NOT NULL, true, false);
            ');

            DB::unprepared('
                CREATE TRIGGER IF NOT EXISTS ' . $table->getTable() . '_is_' . $suffix . '_update_trigger
                BEFORE UPDATE ON ' . $table->getTable() . '
                FOR EACH ROW
                SET NEW.is_' . $suffix . ' = IF(NEW.' . $suffix . '_at IS NOT NULL, true, false);
            ');
        }*/
    }

    private static function dropBooleanTriggers(Blueprint $table, string $suffix): void
    {
        /* if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $table->getTable() . '_is_' . $suffix . '_trigger ON ' . $table->getTable() . ';');
        } else*/
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
        } /*else {
            DB::unprepared('DROP TRIGGER IF EXISTS ' . $table->getTable() . '_is_' . $suffix . '_trigger;');
        }*/
    }
}
