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
        $fieldables_table = CoreTables::Fieldables->value;
        Schema::create($fieldables_table, static function (Blueprint $table) use ($fieldables_table): void {
            $table->id();
            $table->foreignId('preset_id')->nullable(false)->constrained(CoreTables::Presets->value, 'id', "{$fieldables_table}_preset_id_FK")->cascadeOnDelete()->comment('The preset that the field belongs to');
            $table->foreignId('field_id')->nullable(false)->constrained(CoreTables::Fields->value, 'id', "{$fieldables_table}_field_id_FK")->cascadeOnDelete()->comment('The field that the preset belongs to');
            $table->boolean('is_required')->default(false)->nullable(false)->comment('Whether the field is required');
            $table->integer('order_column')->nullable(false)->default(0)->index("{$fieldables_table}_order_column_IDX")->comment('The order of the field');
            $table->json('default')->nullable(true)->comment('The default value of the field');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['preset_id', 'field_id'], "{$fieldables_table}_UN");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Fieldables->value);
    }
};
