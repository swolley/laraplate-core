<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Modules\Core\Models\ModelEmbedding;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = new ModelEmbedding()->getConnection();
        $driver = $connection->getDriverName();

        Schema::connection($connection->getName())->create('model_embeddings', function (Blueprint $table) use ($driver): void {
            $table->id();
            $table->morphs('model', 'embedding_model_IDX');

            if ($driver === 'pgsql') {
                $table->vector('embedding', 1536)->nullable(false)->comment('The generated embedding of the model'); // 1536 dimensions for OpenAI
            } else {
                $table->json('embedding')->nullable(false)->comment('The generated embedding of the model');
            }
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        if ($driver === 'pgsql') {
            DB::connection($connection)->statement('CREATE INDEX idx_model_embeddings_embedding ON model_embeddings USING ivfflat (embedding vector_cosine_ops);');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = new ModelEmbedding()->getConnection();

        Schema::connection($connection)->dropIfExists('model_embeddings');
        Schema::dropIfExists('model_embeddings');
    }
};
