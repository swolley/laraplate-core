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
        $modifications_table = CoreTables::Modifications->value;
        Schema::create($modifications_table, function (Blueprint $table) use ($modifications_table): void {
            $table->id();
            $table->unsignedBigInteger('modifiable_id')->nullable()->comment('The id of the modifiable model');
            $table->string('modifiable_type')->nullable()->comment('The type of the modifiable model');
            $table->unsignedBigInteger('modifier_id')->nullable()->comment('The id of the modifier model');
            $table->string('modifier_type')->nullable()->comment('The type of the modifier model');
            $table->boolean('active')->default(true)->comment('Whether the modification is active');
            $table->string('operation', 16)->default('update')->comment('The operation the modification carries');
            $table->unsignedInteger('approvers_required')->default(1)->comment('The number of approvers required');
            $table->unsignedInteger('disapprovers_required')->default(1)->comment('The number of disapprovers required');
            $table->string('md5')->comment('The md5 hash of the modifications');
            $table->json('modifications')->comment('The modifications');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );

            $table->index(['modifier_id', 'modifier_type'], "{$modifications_table}_modifierable_IDX");
            $table->index(['modifiable_type', 'modifiable_id', 'active', 'operation'], "{$modifications_table}_pending_IDX");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Modifications->value);
    }
};
