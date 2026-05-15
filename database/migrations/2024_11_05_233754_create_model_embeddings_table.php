<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
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

        $supports_vector = $driver === 'pgsql' && DB::connection($connection->getName())->table('pg_available_extensions')->where('name', 'vector')->exists();

        $model_embeddings_table = CoreTables::ModelEmbeddings->value;
        Schema::connection($connection->getName())->create($model_embeddings_table, function (Blueprint $table) use ($supports_vector, $model_embeddings_table): void {
            $table->id();
            $table->morphs('model', "{$model_embeddings_table}_embedding_model_IDX");

            if ($supports_vector) {
                $table->vector('embedding', 1536)->nullable(false)->comment('The generated embedding of the model'); // 1536 dimensions for OpenAI
            } else {
                $table->json('embedding')->nullable(false)->comment('The generated embedding of the model');
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        if ($driver === 'pgsql' && $supports_vector) {
            DB::connection($connection->getName())->statement("CREATE INDEX {$model_embeddings_table}_embedding_IDX ON {$model_embeddings_table} USING ivfflat (embedding vector_cosine_ops);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = new ModelEmbedding()->getConnection();

        Schema::connection($connection->getName())->dropIfExists(CoreTables::ModelEmbeddings->value);
        Schema::dropIfExists(CoreTables::ModelEmbeddings->value);
    }
};
