<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table_name = CoreTables::GraphEdges->value;

        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('edge_key')->unique("{$table_name}_edge_key_UN");
            $table->string('source_module');
            $table->string('source_entity');
            $table->string('source_key');
            $table->string('source_node_id');
            $table->string('target_module');
            $table->string('target_entity');
            $table->string('target_key');
            $table->string('target_node_id');
            $table->string('relation');
            $table->string('relation_path');
            $table->string('type')->nullable();
            $table->boolean('directed')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('stale_at')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->index(['source_node_id', 'stale_at'], "{$table_name}_source_stale_IDX");
            $table->index(['target_node_id', 'stale_at'], "{$table_name}_target_stale_IDX");
            $table->index(['source_module', 'source_entity', 'relation'], "{$table_name}_source_relation_IDX");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::GraphEdges->value);
    }
};
