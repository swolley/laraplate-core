<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Create the presettables pivot with row versioning, field snapshots, and DB triggers.
     *
     * Drivers: mysql, mariadb, pgsql, sqlite, sqlsrv, oracle.
     *
     * Unique / FK: InnoDB reuses the unique index behind `presettables_preset_FK`; the composite
     * unique `presettables_version_UN` on (entity_id, preset_id, version) supports the FK on
     * (entity_id, preset_id).
     *
     * `fields_snapshot`: MySQL/MariaDB reject a literal DEFAULT on JSON; nullable JSON is created
     * first, then {@see applyFieldsSnapshotNotNullConstraint()} runs once the table is still empty.
     */
    public function up(): void
    {
        Schema::create('presettables', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('preset_id')->nullable(false)->comment('The preset that the entity preset relation belongs to');
            $table->unsignedBigInteger('entity_id')->nullable(false)->comment('The entity that the entity preset relation belongs to');
            $table->unsignedInteger('version')->default(1)
                ->comment('Incremental version number scoped to preset+entity');
            $table->json('fields_snapshot')->nullable()
                ->comment('Frozen snapshot of the fields configuration at this version');
            $table->timestamp('created_at')->nullable()->useCurrent();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: false,
                hasSoftDelete: true,
            );

            $table->foreign(['entity_id', 'preset_id'], 'presettables_preset_FK')
                ->references(['entity_id', 'id'])
                ->on('presets')
                ->cascadeOnDelete();
            $table->unique(['entity_id', 'preset_id', 'version'], 'presettables_version_UN');
        });

        $this->applyFieldsSnapshotNotNullConstraint();
        $this->createTriggers();
    }

    public function down(): void
    {
        $this->dropTriggers();
        Schema::dropIfExists('presettables');
    }

    /**
     * Enforce NOT NULL on fields_snapshot after the table is created (same on MySQL, PostgreSQL, SQLite, etc.).
     */
    private function applyFieldsSnapshotNotNullConstraint(): void
    {
        if (DB::table('presettables')->whereNull('fields_snapshot')->exists()) {
            throw new RuntimeException(
                'Cannot enforce NOT NULL on presettables.fields_snapshot: null values remain.',
            );
        }

        Schema::table('presettables', static function (Blueprint $table): void {
            $table->json('fields_snapshot')->nullable(false)->change();
        });
    }

    /**
     * Create database-specific triggers.
     */
    private function createTriggers(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => $this->createMySQLTriggers(),
            'pgsql' => $this->createPostgreSQLTriggers(),
            'sqlite' => $this->createSQLiteTriggers(),
            'sqlsrv' => $this->createSQLServerTriggers(),
            'oracle' => $this->createOracleTriggers(),
            default => throw new RuntimeException('Unsupported database driver: ' . DB::getDriverName()),
        };
    }

    /**
     * Drop database-specific triggers.
     */
    private function dropTriggers(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => $this->dropMySQLTriggers(),
            'pgsql' => $this->dropPostgreSQLTriggers(),
            'sqlite' => $this->dropSQLiteTriggers(),
            'sqlsrv' => $this->dropSQLServerTriggers(),
            'oracle' => $this->dropOracleTriggers(),
            default => throw new RuntimeException('Unsupported database driver: ' . DB::getDriverName()),
        };
    }

    private function createPostgreSQLTriggers(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION set_presettable_version()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.version := COALESCE(
                    (SELECT MAX(version) FROM presettables
                     WHERE preset_id = NEW.preset_id AND entity_id = NEW.entity_id), 0
                ) + 1;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presettables_version
            BEFORE INSERT ON presettables
            FOR EACH ROW EXECUTE FUNCTION set_presettable_version();
        ');

        DB::unprepared('
            CREATE OR REPLACE FUNCTION sync_presettables_on_preset()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    INSERT INTO presettables (entity_id, preset_id, fields_snapshot, deleted_at)
                    VALUES (NEW.entity_id, NEW.id, \'[]\', NEW.deleted_at);
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    IF NEW.deleted_at IS DISTINCT FROM OLD.deleted_at THEN
                        UPDATE presettables
                        SET deleted_at = NEW.deleted_at
                        WHERE preset_id = NEW.id AND deleted_at IS NULL;
                    END IF;
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    DELETE FROM presettables WHERE preset_id = OLD.id;
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_insert
            AFTER INSERT ON presets
            FOR EACH ROW EXECUTE FUNCTION sync_presettables_on_preset();
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update
            AFTER UPDATE ON presets
            FOR EACH ROW EXECUTE FUNCTION sync_presettables_on_preset();
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_delete
            AFTER DELETE ON presets
            FOR EACH ROW EXECUTE FUNCTION sync_presettables_on_preset();
        ');
    }

    private function dropPostgreSQLTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presettables_version ON presettables');
        DB::unprepared('DROP FUNCTION IF EXISTS set_presettable_version()');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert ON presets');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update ON presets');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete ON presets');
        DB::unprepared('DROP FUNCTION IF EXISTS sync_presettables_on_preset()');
    }

    private function createMySQLTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_presettables_version BEFORE INSERT ON presettables
            FOR EACH ROW
            SET NEW.version = COALESCE(
                (SELECT MAX(version) FROM presettables
                 WHERE preset_id = NEW.preset_id AND entity_id = NEW.entity_id), 0
            ) + 1;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_insert AFTER INSERT ON presets
            FOR EACH ROW
            INSERT INTO presettables (entity_id, preset_id, fields_snapshot, deleted_at)
            VALUES (NEW.entity_id, NEW.id, \'[]\', NEW.deleted_at);
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update AFTER UPDATE ON presets
            FOR EACH ROW
            BEGIN
                IF NEW.deleted_at <> OLD.deleted_at OR (NEW.deleted_at IS NULL) <> (OLD.deleted_at IS NULL) THEN
                    UPDATE presettables
                    SET deleted_at = NEW.deleted_at
                    WHERE preset_id = NEW.id AND deleted_at IS NULL;
                END IF;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_delete AFTER DELETE ON presets
            FOR EACH ROW
            DELETE FROM presettables WHERE preset_id = OLD.id;
        ');
    }

    private function dropMySQLTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presettables_version');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete');
    }

    private function createSQLiteTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_presets_insert AFTER INSERT ON presets
            BEGIN
                INSERT INTO presettables (entity_id, preset_id, fields_snapshot, version, deleted_at)
                VALUES (NEW.entity_id, NEW.id, \'[]\',
                    COALESCE((SELECT MAX(version) FROM presettables WHERE preset_id = NEW.id AND entity_id = NEW.entity_id), 0) + 1,
                    NEW.deleted_at);
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update AFTER UPDATE ON presets
            WHEN NEW.deleted_at IS NOT OLD.deleted_at
            BEGIN
                UPDATE presettables
                SET deleted_at = NEW.deleted_at
                WHERE preset_id = NEW.id AND deleted_at IS NULL;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_delete AFTER DELETE ON presets
            BEGIN
                DELETE FROM presettables WHERE preset_id = OLD.id;
            END;
        ');
    }

    private function dropSQLiteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete');
    }

    private function createSQLServerTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_presettables_version ON presettables
            INSTEAD OF INSERT
            AS
            BEGIN
                SET NOCOUNT ON;
                INSERT INTO presettables (entity_id, preset_id, version, fields_snapshot, created_at, deleted_at)
                SELECT i.entity_id, i.preset_id,
                    COALESCE((SELECT MAX(version) FROM presettables p
                              WHERE p.preset_id = i.preset_id AND p.entity_id = i.entity_id), 0) + 1,
                    i.fields_snapshot, i.created_at, i.deleted_at
                FROM inserted i;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_insert ON presets
            AFTER INSERT
            AS
            BEGIN
                SET NOCOUNT ON;
                INSERT INTO presettables (entity_id, preset_id, fields_snapshot, deleted_at)
                SELECT entity_id, id, \'[]\', deleted_at
                FROM inserted;
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update ON presets
            AFTER UPDATE
            AS
            BEGIN
                SET NOCOUNT ON;
                UPDATE presettables
                SET deleted_at = i.deleted_at
                FROM presettables ep
                INNER JOIN inserted i ON ep.preset_id = i.id
                INNER JOIN deleted d ON ep.preset_id = d.id
                WHERE (i.deleted_at IS NULL AND d.deleted_at IS NOT NULL)
                   OR (i.deleted_at IS NOT NULL AND d.deleted_at IS NULL)
                   OR (i.deleted_at <> d.deleted_at);
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_delete ON presets
            AFTER DELETE
            AS
            BEGIN
                SET NOCOUNT ON;
                DELETE FROM presettables
                WHERE preset_id IN (SELECT id FROM deleted);
            END;
        ');
    }

    private function dropSQLServerTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presettables_version');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete');
    }

    private function createOracleTriggers(): void
    {
        DB::unprepared('
            CREATE OR REPLACE TRIGGER trg_presettables_version
            BEFORE INSERT ON presettables
            FOR EACH ROW
            DECLARE
                v_max_version NUMBER;
            BEGIN
                SELECT COALESCE(MAX(version), 0) + 1 INTO v_max_version
                FROM presettables
                WHERE preset_id = :NEW.preset_id AND entity_id = :NEW.entity_id;
                :NEW.version := v_max_version;
            END;
        ');

        DB::unprepared('
            CREATE OR REPLACE TRIGGER trg_presets_insert
            AFTER INSERT ON presets
            FOR EACH ROW
            BEGIN
                INSERT INTO presettables (entity_id, preset_id, fields_snapshot, deleted_at)
                VALUES (:NEW.entity_id, :NEW.id, \'[]\', :NEW.deleted_at);
            END;
        ');

        DB::unprepared('
            CREATE OR REPLACE TRIGGER trg_presets_update
            AFTER UPDATE ON presets
            FOR EACH ROW
            BEGIN
                IF (:NEW.deleted_at IS NULL AND :OLD.deleted_at IS NOT NULL)
                   OR (:NEW.deleted_at IS NOT NULL AND :OLD.deleted_at IS NULL) THEN
                    UPDATE presettables
                    SET deleted_at = :NEW.deleted_at
                    WHERE preset_id = :NEW.id AND deleted_at IS NULL;
                END IF;
            END;
        ');

        DB::unprepared('
            CREATE OR REPLACE TRIGGER trg_presets_delete
            AFTER DELETE ON presets
            FOR EACH ROW
            BEGIN
                DELETE FROM presettables WHERE preset_id = :OLD.id;
            END;
        ');
    }

    private function dropOracleTriggers(): void
    {
        DB::unprepared('DROP TRIGGER trg_presettables_version');
        DB::unprepared('DROP TRIGGER trg_presets_insert');
        DB::unprepared('DROP TRIGGER trg_presets_update');
        DB::unprepared('DROP TRIGGER trg_presets_delete');
    }
};
