<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\ModelEmbedding;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = (new ModelEmbedding)->getConnection();
        $table_name = CoreTables::ModelEmbeddings->value;

        $connection->getSchemaBuilder()->table($table_name, function (Blueprint $table): void {
            $table->string('content_hash', 64)->nullable()
                ->comment('SHA-256 of the embedded text; lets indexing skip re-embedding unchanged content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = (new ModelEmbedding)->getConnection();
        $table_name = CoreTables::ModelEmbeddings->value;

        $connection->getSchemaBuilder()->table($table_name, function (Blueprint $table): void {
            $table->dropColumn('content_hash');
        });
    }
};
