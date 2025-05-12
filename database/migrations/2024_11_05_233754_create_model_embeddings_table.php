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
        Schema::create('model_embeddings', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model', 'embedding_model_IDX');
            $table->json('embedding')->nullable(false)->comment('The generated embedding of the model');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
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
