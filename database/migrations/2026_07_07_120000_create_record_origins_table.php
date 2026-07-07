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
        $table_name = CoreTables::RecordOrigins->value;
        Schema::create($table_name, static function (Blueprint $table) use ($table_name): void {
            $table->id();
            $table->morphs('referable');
            $table->string('source_key')->comment('Machine key of the origin source, e.g. naxos_api');
            $table->string('source_label')->nullable()->comment('Human-readable name of the origin source');
            $table->string('external_id')->nullable()->comment('Identifier of the record in the origin source, null for manual origins');
            $table->string('url', 2048)->nullable()->comment('Link to the record in the origin source');

            MigrateUtils::timestamps($table, hasCreateUpdate: true);

            $table->unique(['referable_type', 'source_key', 'external_id'], "{$table_name}_identity_UN");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::RecordOrigins->value);
    }
};
