<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fields', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable(false)->comment('The name of the field');
            $table->string('type')->nullable(false)->comment('The type of the field');
            $table->json('options')->nullable(false)->comment('The options of the field');
            $table->boolean('is_slug')->default(false)->nullable(false)->comment('Whether the field takes part in the slug');
            $table->boolean('is_active')->default(true)->nullable(false)->index('fields_is_active_IDX')->comment('Whether the field is active');
            $table->boolean('is_translatable')->default(false)->nullable(false)->comment('Whether the field is translatable');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['name', 'deleted_at'], 'fields_name_UN');
        });

        Schema::create('fieldables', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preset_id')->nullable(false)->constrained('presets', 'id', 'fieldables_preset_id_FK')->cascadeOnDelete()->comment('The preset that the field belongs to');
            $table->foreignId('field_id')->nullable(false)->constrained('fields', 'id', 'fieldables_field_id_FK')->cascadeOnDelete()->comment('The field that the preset belongs to');
            $table->boolean('is_required')->default(false)->nullable(false)->comment('Whether the field is required');
            $table->integer('order_column')->nullable(false)->default(0)->index('fieldables_order_column_IDX')->comment('The order of the field');
            $table->json('default')->nullable(true)->comment('The default value of the field');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['preset_id', 'field_id'], 'fieldables_UN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
