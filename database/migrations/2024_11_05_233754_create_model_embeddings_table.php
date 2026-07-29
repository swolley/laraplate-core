<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    private const int DEFAULT_VECTOR_DIMENSIONS = 1536;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = app('db')->connection();
        $supports_vector = $this->supportsPostgreSQLVector($connection);
        $vector_dimensions = $this->vectorDimensions();

        $model_embeddings_table = CoreTables::ModelEmbeddings->value;
        $connection->getSchemaBuilder()->create($model_embeddings_table, function (Blueprint $table) use ($connection, $supports_vector, $model_embeddings_table, $vector_dimensions): void {
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
                connection: $connection,
            );
        });

        if ($supports_vector) {
            $grammar = $connection->getQueryGrammar();
            $wrapped_index = $grammar->wrap("{$model_embeddings_table}_embedding_IDX");
            $wrapped_table = $grammar->wrapTable($model_embeddings_table);
            $wrapped_embedding = $grammar->wrap('embedding');
            $connection->statement("CREATE INDEX {$wrapped_index} ON {$wrapped_table} USING ivfflat ({$wrapped_embedding} vector_cosine_ops);");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app('db')->connection()->getSchemaBuilder()->dropIfExists(CoreTables::ModelEmbeddings->value);
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
