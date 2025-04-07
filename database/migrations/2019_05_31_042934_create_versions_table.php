<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationFunctions;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('versions', function (Blueprint $table) {
            $uuid = config('versionable.uuid');

            $uuid ? $table->uuid('id')->primary() : $table->bigIncrements('id');
            $table->unsignedBigInteger(config('versionable.user_foreign_key', 'user_id'));

            $uuid ? $table->uuidMorphs('versionable') : $table->morphs('versionable');

            // TODO: serve aggiungere un indice su versionable_type e versionable_id?

            $table->json('contents')->nullable();
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('versions');
    }
};
