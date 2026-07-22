<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Helpers\MigrateUtils;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table_name = CoreTables::VersionSets->value;
        $uses_oracle = Schema::getConnection()->getDriverName() === 'oracle';

        Schema::create($table_name, static function (Blueprint $table) use ($table_name, $uses_oracle): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('root_type')->nullable();
            $table->string('root_id')->nullable();
            $table->string('root_connection_ref')->nullable();
            $table->string('root_table_ref')->nullable();
            $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable();
            $table->enum(
                'kind',
                array_map(static fn (VersionSetKind $case): string => $case->value, VersionSetKind::cases()),
            );
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('reverted_from_set_id')->nullable();

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                createdAtIndexName: 'vsets_created_IDX',
            );

            $table->unique('uuid', 'vsets_uuid_UN');
            $table->index(['root_type', 'root_id'], 'vsets_root_IDX');
            $reverted_from_foreign_key = $table->foreign('reverted_from_set_id', 'vsets_reverted_FK')
                ->references('id')
                ->on($table_name);

            // Oracle's default NO ACTION is restrictive; its DDL rejects ON DELETE RESTRICT.
            if (! $uses_oracle) {
                $reverted_from_foreign_key->restrictOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::VersionSets->value);
    }
};
