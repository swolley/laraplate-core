<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $connection = (new ModelEmbedding)->getConnection();
        $supports_vector = $this->supportsPostgreSQLVector($connection);

        $model_embeddings_table = CoreTables::ModelEmbeddings->value;
        $connection->getSchemaBuilder()->create($model_embeddings_table, function (Blueprint $table) use ($connection, $supports_vector, $model_embeddings_table): void {
            $table->id();
            $table->morphs('model', "{$model_embeddings_table}_embedding_model_IDX");

            if ($supports_vector) {
                $table->vector('embedding')->nullable(false)->comment('The generated embedding of the model; no dimension, so models of different lengths coexist (an index per profile is created on demand)');
            } else {
                $table->json('embedding')->nullable(false)->comment('The generated embedding of the model');
            }

            // Provenance of the vector: which translation it was derived from
            // (null for non-translated models) and which embedding-model profile
            // produced it (enables incremental per-locale re-embed and stale detection).
            $table->string('locale')->nullable()->comment('Translation locale this vector was derived from; null for non-translated models');
            $table->string('model_key')->nullable()->comment('Embedding model profile key that produced this vector');
            $table->index(['model_type', 'model_id', 'locale'], "{$model_embeddings_table}_model_locale_IDX");
            $table->string('content_hash', 64)->nullable()
                ->comment('SHA-256 of the embedded text; lets indexing skip re-embedding unchanged content');
            // Lookup of an identical text already embedded by the same model, so a duplicated
            // file or caption reuses its vectors instead of calling the embedding service again.
            $table->index(['content_hash', 'model_key'], "{$model_embeddings_table}_content_model_IDX");

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                connection: $connection,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        (new ModelEmbedding)->getConnection()->getSchemaBuilder()->dropIfExists(CoreTables::ModelEmbeddings->value);
    }

    private function supportsPostgreSQLVector(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return false;
        }

        if (! $connection->table('pg_available_extensions')->where('name', 'vector')->exists()) {
            return false;
        }

        $connection->statement('CREATE EXTENSION IF NOT EXISTS vector');

        return $connection->table('pg_extension')->where('extname', 'vector')->exists();
    }
};
