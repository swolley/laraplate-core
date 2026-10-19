<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Helpers\MigrateUtils;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $modules_table = CoreTables::Modules->value;
        Schema::create($modules_table, function (Blueprint $table) use ($modules_table): void {
            $table->id();
            $table->string('name')->nullable(false)->unique("{$modules_table}_name_UQ")->comment('The name of the module');
            $table->boolean('is_active')->nullable(false)->default(true)->index("{$modules_table}_is_active_IDX")->comment('Is the module active');
            $table->string('url')->nullable()->comment('The url of the frontend app of the module');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Modules->value);
    }
};
