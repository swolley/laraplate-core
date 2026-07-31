<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;

return new class() extends Migration
{
    public function up(): void
    {
        $settings_table = CoreTables::Settings->value;

        Schema::table($settings_table, function (Blueprint $table) use ($settings_table): void {
            $table->string('module')
                ->nullable(true)
                ->index("{$settings_table}_module_IDX")
                ->comment('Owning module, null when the setting was not written by a seeder');

            $table->json('seeded_value')
                ->nullable(true)
                ->comment('Last value written by the seeder; drift is value !== seeded_value');
        });
    }

    public function down(): void
    {
        $settings_table = CoreTables::Settings->value;

        Schema::table($settings_table, function (Blueprint $table) use ($settings_table): void {
            $table->dropIndex("{$settings_table}_module_IDX");
            $table->dropColumn(['module', 'seeded_value']);
        });
    }
};
