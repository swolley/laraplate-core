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
        Schema::create('taxonomies', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->nullable(false)->constrained('entities', 'id', 'taxonomies_entity_id_FK')->cascadeOnDelete()->comment('The entity that the taxonomy belongs to');
            $table->foreignId('presettable_id')->nullable(false)->constrained('presettables', 'id', 'taxonomies_presettable_id_FK')->cascadeOnDelete()->comment('The entity preset that the taxonomy belongs to');
            $table->json('shared_components')->nullable()->comment('The shared dynamic components of the taxonomy');
            $table->foreignId('parent_id')->nullable(true)->constrained('taxonomies', 'id', 'taxonomies_parent_id_FK')->nullOnDelete()->comment('The parent taxonomy');
            $table->string('logo')->nullable(true)->comment('The logo of the taxonomy');
            $table->string('logo_full')->nullable(true)->comment('The full logo of the taxonomy');
            $table->boolean('is_active')->default(true)->nullable(false)->index('taxonomies_is_active_IDX')->comment('Whether the taxonomy is active');
            $table->integer('order_column')->nullable(false)->default(0)->index('taxonomies_order_column_IDX')->comment('The order of the taxonomy');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
                hasValidity: true,
            );

            // Unique constraints for name and slug are now in taxonomies_translations table (per locale)
            $table->unique(['id', 'parent_id'], 'taxonomy_parent_UN');
            $table->unique(['id', 'entity_id'], 'taxonomy_entity_UN');
        });

        // Evita auto-relazione (categoria che punta sé stessa)
        $driver_name = DB::getDriverName();

        if ($driver_name === 'pgsql') {
            DB::statement('ALTER TABLE taxonomies ADD CONSTRAINT taxonomies_parent_id_check CHECK (parent_id <> id)');
        } elseif (in_array($driver_name, ['mysql', 'mariadb'], true)) {
            // MySQL/MariaDB non consentono il CHECK con colonna usata da una FK (errore 3823),
            // quindi usiamo trigger per bloccare parent_id = id.
            DB::unprepared('
                CREATE TRIGGER taxonomies_parent_check_insert
                BEFORE INSERT ON taxonomies
                FOR EACH ROW
                BEGIN
                    IF NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'parent_id cannot reference self\';
                    END IF;
                END;
            ');

            DB::unprepared('
                CREATE TRIGGER taxonomies_parent_check_update
                BEFORE UPDATE ON taxonomies
                FOR EACH ROW
                BEGIN
                    IF NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id THEN
                        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'parent_id cannot reference self\';
                    END IF;
                END;
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
    }
};
