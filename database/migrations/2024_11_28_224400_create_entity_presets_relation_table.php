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
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('presettables', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('preset_id')->nullable(false)->comment('The preset that the entity preset relation belongs to');
            $table->unsignedBigInteger('entity_id')->nullable(false)->comment('The entity that the entity preset relation belongs to');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: false,
                hasSoftDelete: true,
            );

            $table->foreign(['entity_id', 'preset_id'], 'presettables_preset_FK')
                ->references(['entity_id', 'id'])
                ->on('presets')
                ->cascadeOnDelete();
            $table->unique(['entity_id', 'preset_id'], 'presettables_preset_UN');
        });

        $this->createTriggers();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropTriggers();
        Schema::dropIfExists('presettables');
    }

    /**
     * Create database-specific triggers.
     */
    private function createTriggers(): void
    {
        $driver = DB::getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => $this->createMySQLTriggers(),
            'pgsql' => $this->createPostgreSQLTriggers(),
            'sqlite' => $this->createSQLiteTriggers(),
            'sqlsrv' => $this->createSQLServerTriggers(),
            'oracle' => $this->createOracleTriggers(),
            default => throw new Exception("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Drop database-specific triggers.
     */
    private function dropTriggers(): void
    {
        $driver = DB::getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => $this->dropMySQLTriggers(),
            'pgsql' => $this->dropPostgreSQLTriggers(),
            'sqlite' => $this->dropSQLiteTriggers(),
            'sqlsrv' => $this->dropSQLServerTriggers(),
            'oracle' => $this->dropOracleTriggers(),
            default => throw new Exception("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * MySQL Triggers.
     */
    private function createMySQLTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_presets_insert AFTER INSERT ON presets
            FOR EACH ROW
            INSERT INTO presettables (entity_id, preset_id, deleted_at)
            VALUES (NEW.entity_id, NEW.id, NEW.deleted_at);
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update AFTER UPDATE ON presets
            FOR EACH ROW
            UPDATE presettables
            SET entity_id = NEW.entity_id,
                updated_at = NEW.updated_at,
                deleted_at = NEW.deleted_at
            WHERE preset_id = NEW.id;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_delete AFTER DELETE ON presets
            FOR EACH ROW
            DELETE FROM presettables
            WHERE preset_id = OLD.id;
        ');
    }

    private function dropMySQLTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete');
    }

    /**
     * PostgreSQL Triggers.
     */
    private function createPostgreSQLTriggers(): void
    {
        // Create trigger function
        DB::unprepared('
            CREATE OR REPLACE FUNCTION sync_presettables()
            RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = \'INSERT\' THEN
                    INSERT INTO presettables (entity_id, preset_id, deleted_at)
                    VALUES (NEW.entity_id, NEW.id, NEW.deleted_at);
                    RETURN NEW;
                ELSIF TG_OP = \'UPDATE\' THEN
                    UPDATE presettables
                    SET entity_id = NEW.entity_id, deleted_at = NEW.deleted_at
                    WHERE preset_id = NEW.id;
                    RETURN NEW;
                ELSIF TG_OP = \'DELETE\' THEN
                    DELETE FROM presettables WHERE preset_id = OLD.id;
                    RETURN OLD;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create triggers
        DB::unprepared('
            CREATE TRIGGER trg_presets_insert
            AFTER INSERT ON presets
            FOR EACH ROW EXECUTE FUNCTION sync_presettables();
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update
            AFTER UPDATE ON presets
            FOR EACH ROW EXECUTE FUNCTION sync_presettables();
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_delete
            AFTER DELETE ON presets
            FOR EACH ROW EXECUTE FUNCTION sync_presettables();
        ');
    }

    private function dropPostgreSQLTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert ON presets');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update ON presets');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete ON presets');
        DB::unprepared('DROP FUNCTION IF EXISTS sync_presettables()');
    }

    /**
     * SQLite Triggers (limited support).
     */
    private function createSQLiteTriggers(): void
    {
        // SQLite has limited trigger support, but we can create basic ones
        DB::unprepared('
            CREATE TRIGGER trg_presets_insert AFTER INSERT ON presets
            BEGIN
                INSERT INTO presettables (entity_id, preset_id, deleted_at)
                VALUES (NEW.entity_id, NEW.id, NEW.deleted_at);
            END;
        ');

        DB::unprepared('
            CREATE TRIGGER trg_presets_update AFTER UPDATE ON presets
            BEGIN
                UPDATE presettables
                SET entity_id = NEW.entity_id, deleted_at = NEW.deleted_at
                WHERE preset_id = NEW.id;
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

    /**
     * SQL Server Triggers.
     */
    private function createSQLServerTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER trg_presets_insert ON presets
            AFTER INSERT
            AS
            BEGIN
                SET NOCOUNT ON;
                INSERT INTO presettables (entity_id, preset_id, deleted_at)
                SELECT entity_id, id, deleted_at
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
                SET entity_id = i.entity_id, deleted_at = i.deleted_at
                FROM presettables ep
                INNER JOIN inserted i ON ep.preset_id = i.id;
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
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_presets_delete');
    }

    /**
     * Oracle Database Triggers.
     */
    private function createOracleTriggers(): void
    {
        // Create trigger function (PL/SQL)
        DB::unprepared('
            CREATE OR REPLACE TRIGGER trg_presets_insert
            AFTER INSERT ON presets
            FOR EACH ROW
            BEGIN
                INSERT INTO presettables (entity_id, preset_id, deleted_at)
                VALUES (:NEW.entity_id, :NEW.id, :NEW.deleted_at);
            END;
        ');

        DB::unprepared('
            CREATE OR REPLACE TRIGGER trg_presets_update
            AFTER UPDATE ON presets
            FOR EACH ROW
            BEGIN
                UPDATE presettables
                SET entity_id = :NEW.entity_id, deleted_at = :NEW.deleted_at
                WHERE preset_id = :NEW.id;
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
        DB::unprepared('DROP TRIGGER trg_presets_insert');
        DB::unprepared('DROP TRIGGER trg_presets_update');
        DB::unprepared('DROP TRIGGER trg_presets_delete');
    }
};
