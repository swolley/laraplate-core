<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;
use Modules\Core\Import\Enums\ImportSessionStatus;
use Modules\Core\Import\Enums\ImportSourceFormat;

return new class extends Migration
{
    public function up(): void
    {
        $table_name = CoreTables::ImportSessions->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained(CoreTables::Users->value, 'id', "{$table_name}_user_FK")
                ->nullOnDelete();
            $table->string('entity_key')->comment('The registered import entity, e.g. core.user or sao.ticket');
            $table->enum('source_format', ImportSourceFormat::values());
            $table->string('file_disk');
            $table->string('file_path');
            $table->string('original_filename');
            $table->enum('status', ImportSessionStatus::values())->default(ImportSessionStatus::Draft->value);
            $table->json('detected_columns')->nullable()->comment('Source column headers detected on upload');
            $table->json('mapping')->nullable()->comment('Target field name => source column header');
            $table->json('options')->nullable()->comment('Reader hints: delimiter, etc.');
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->index(['entity_key', 'status'], "{$table_name}_entity_status_IDX");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::ImportSessions->value);
    }
};
