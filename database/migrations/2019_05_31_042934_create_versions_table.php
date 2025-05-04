<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('versions', function (Blueprint $table): void {
            $uuid = config('versionable.uuid');

            $uuid ? $table->uuid('id')->primary() : $table->bigIncrements('id');
            $table->string('connection_ref')->nullable()->comment('The connection reference of the version');
            $table->string('table_ref')->nullable()->comment('The table reference of the version');
            $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'))->nullable(true)->comment('The user that created the version');

            $uuid ? $table->uuidMorphs('versionable', 'versionable_morph_idx') : $table->morphs('versionable', 'versionable_morph_idx');

            // TODO: serve aggiungere un indice su versionable_type e versionable_id?

            $table->json('contents')->nullable()->comment('The changed model attributes');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->index(['versionable_id', 'versionable_type'], 'versions_versionable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
