<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $presets_table = CoreTables::Presets->value;
        Schema::create($presets_table, static function (Blueprint $table) use ($presets_table): void {
            $table->id();
            $table->foreignId('entity_id')->nullable(false)->constrained(CoreTables::Entities->value, 'id', "{$presets_table}_entity_id_FK")->cascadeOnDelete()->comment('The entity that the preset belongs to');
            $table->string('name')->nullable(false)->comment('The name of the preset');
            $table->boolean('is_active')->default(true)->nullable(false)->index("{$presets_table}_is_active_IDX")->comment('Whether the preset is active');
            $table->boolean('is_default')->default(false)->nullable(false)->index("{$presets_table}_is_default_IDX")->comment('Whether the preset is the default preset for same entity');
            $table->foreignId('template_id')->nullable(true)->constrained(CoreTables::Templates->value, 'id', "{$presets_table}_template_id_FK")->cascadeOnDelete()->comment('The template that the preset belongs to');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['entity_id', 'name', 'deleted_at'], "{$presets_table}_UN");
            $table->unique(['entity_id', 'id'], "{$presets_table}_ids_UN");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Presets->value);
    }
};
