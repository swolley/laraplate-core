<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Helpers\MigrateUtils;
use Overtrue\LaravelVersionable\VersionStrategy;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table_name = CoreTables::Versions->value;
        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $uuid = config('versionable.uuid');

            $uuid ? $table->uuid('id')->primary() : $table->bigIncrements('id');
            $table->unsignedBigInteger('version_set_id')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->enum(
                'change_type',
                array_map(static fn (VersionChangeType $case): string => $case->value, VersionChangeType::cases()),
            )->nullable();
            $table->string('relation_path')->nullable();
            $table->json('subject_key')->nullable();
            $table->string('connection_ref')->nullable()->comment('The connection reference of the version');
            $table->string('table_ref')->nullable()->comment('The table reference of the version');
            $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable(true)->comment('The user that created the version');

            $uuid ? $table->uuidMorphs('versionable', "{$table_name}_morph_idx") : $table->morphs('versionable', "{$table_name}_morph_idx");

            $table->json('original_contents')
                ->nullable()
                ->comment('Original model attributes before the change');
            $table->json('contents')->nullable()->comment('The changed model attributes');
            $table->enum(
                'version_strategy',
                array_map(static fn (VersionStrategy $case): string => $case->value, VersionStrategy::cases()),
            )->comment('Strategy used when this row was created');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index(['versionable_id', 'versionable_type'], "{$table_name}_versionable_IDX");
            $table->unique(['version_set_id', 'sequence'], 'versions_set_sequence_UN');
            $table->foreign('version_set_id', 'versions_set_FK')
                ->references('id')
                ->on(CoreTables::VersionSets->value)
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Versions->value);
    }
};
