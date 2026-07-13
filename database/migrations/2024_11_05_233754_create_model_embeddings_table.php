<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\Core\Models\ModelEmbedding;

return new class extends Migration
{
    private const int DEFAULT_VECTOR_DIMENSIONS = 1536;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = new ModelEmbedding()->getConnection();
        $connection_name = $connection->getName();
        $supports_vector = $this->supportsPostgreSQLVector($connection);
        $vector_dimensions = $this->vectorDimensions();

        $model_embeddings_table = CoreTables::ModelEmbeddings->value;
        Schema::connection($connection_name)->create($model_embeddings_table, function (Blueprint $table) use ($supports_vector, $model_embeddings_table, $vector_dimensions): void {
            $table->id();
            $table->morphs('model', "{$model_embeddings_table}_embedding_model_IDX");

            if ($supports_vector) {
                $table->vector('embedding', $vector_dimensions)->nullable(false)->comment('The generated embedding of the model');
            } else {
                $table->json('embedding')->nullable(false)->comment('The generated embedding of the model');
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        if ($supports_vector) {
            DB::connection($connection_name)->statement("CREATE INDEX {$model_embeddings_table}_embedding_IDX ON {$model_embeddings_table} USING ivfflat (embedding vector_cosine_ops);");
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

    private function supportsPostgreSQLVector(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return false;
        }

        $database = DB::connection($connection->getName());

        if (! $database->table('pg_available_extensions')->where('name', 'vector')->exists()) {
            return false;
        }

        $database->statement('CREATE EXTENSION IF NOT EXISTS vector');

        return $database->table('pg_extension')->where('extname', 'vector')->exists();
    }

    private function vectorDimensions(): int
    {
        $dimensions = config(
            'search.vector.dimensions',
            config('search.vector_search.dimension', self::DEFAULT_VECTOR_DIMENSIONS),
        );

        if (! is_numeric($dimensions) || (int) $dimensions < 1) {
            return self::DEFAULT_VECTOR_DIMENSIONS;
        }

        return (int) $dimensions;
    }
};
