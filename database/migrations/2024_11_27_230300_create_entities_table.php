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
        $entities_table = CoreTables::Entities->value;
        Schema::create($entities_table, static function (Blueprint $table) use ($entities_table): void {
            $table->id();
            $table->string('name')->nullable(false)->comment('The name of the entity')->unique("{$entities_table}_name_UN");
            $table->string('slug')->nullable(false)->comment('The slug of the entity')->unique("{$entities_table}_slug_UN");
            $table->string('type')->nullable(false)->index("{$entities_table}_type_IDX")->comment('The type of the entity');
            $table->boolean('is_active')->default(true)->nullable(false)->index("{$entities_table}_is_active_IDX")->comment('Whether the entity is active');
            $table->boolean('is_default')->default(false)->nullable(false)->index("{$entities_table}_is_default_IDX")->comment('Whether the entity is the default entity for same type');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Entities->value);
    }
};
