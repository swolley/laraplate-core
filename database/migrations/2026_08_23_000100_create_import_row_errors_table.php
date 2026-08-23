<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CoreTables::ImportRowErrors->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('import_session_id')
                ->constrained(CoreTables::ImportSessions->value, 'id', "{$table_name}_session_FK")
                ->cascadeOnDelete();
            $table->unsignedInteger('row_number')->comment('1-based data row number in the source (header excluded)');
            $table->json('errors')->comment('Field name => list of messages; "_" for row-level');
            $table->json('raw')->nullable()->comment('The raw mapped row that failed, for the downloadable report');

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->index(['import_session_id', 'row_number'], "{$table_name}_session_row_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::ImportRowErrors->value);
    }
};
