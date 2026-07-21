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
        $table_name = CoreTables::OutboxEvents->value;

        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type')->index();
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('publish_attempts')->default(0);
            $table->text('last_error')->nullable();

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->index(['aggregate_type', 'aggregate_id'], "{$table_name}_aggregate_IN");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CoreTables::OutboxEvents->value);
    }
};
