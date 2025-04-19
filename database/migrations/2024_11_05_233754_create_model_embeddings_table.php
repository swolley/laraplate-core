<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationFunctions;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('model_embeddings', function (Blueprint $table) {
            $table->id();
            $table->morphs('model', 'embedding_model_IDX');
            $table->json('embedding')->nullable(false)->comment('The generated embedding of the model');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_embeddings');
    }
};
