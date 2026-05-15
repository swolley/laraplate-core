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
        $templates_table = CoreTables::Templates->value;
        Schema::create($templates_table, static function (Blueprint $table) use ($templates_table): void {
            $table->id();
            $table->string('name')->nullable(false)->comment('The name of the template');
            $table->longText('content')->nullable(false)->comment('The blade template content');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['name', 'deleted_at'], "{$templates_table}_UN");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Templates->value);
    }
};
