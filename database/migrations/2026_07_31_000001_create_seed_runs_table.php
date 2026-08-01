<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class() extends Migration
{
    public function up(): void
    {
        $table_name = CoreTables::SeedRuns->value;

        Schema::create($table_name, function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->string('run_id')->nullable(false)->index("{$table_name}_run_id_IDX")
                ->comment('Identifies one invocation of the orchestrator');
            $table->string('node')->nullable(false)->comment('Seeder class executed');
            $table->string('status', 20)->nullable(false)->comment('running, succeeded or failed');
            $table->string('content_hash')->nullable(true)
                ->comment('Hash of the definitions this node would write');
            $table->timestamp('started_at')->nullable(false);
            $table->timestamp('finished_at')->nullable(true);
            $table->text('error')->nullable(true);

            $table->unique(['run_id', 'node'], "{$table_name}_run_node_UN");

            MigrateUtils::timestamps($table, hasCreateUpdate: true, hasSoftDelete: false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::SeedRuns->value);
    }
};
