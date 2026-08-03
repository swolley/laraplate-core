<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table_name = CoreTables::RecordOrigins->value;

        Schema::table($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->char('fingerprint', 64)->nullable()->after('external_id');
            $table->timestamp('source_updated_at')->nullable()->after('url');
            $table->index(['source_key', 'source_updated_at'], "{$table_name}_source_updated_IDX");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table_name = CoreTables::RecordOrigins->value;

        Schema::table($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->dropIndex("{$table_name}_source_updated_IDX");
            $table->dropColumn(['fingerprint', 'source_updated_at']);
        });
    }
};
