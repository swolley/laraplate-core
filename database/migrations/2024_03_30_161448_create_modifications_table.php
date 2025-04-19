<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationFunctions;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create('modifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('modifiable_id')->nullable()->comment('The id of the modifiable model');
            $table->string('modifiable_type')->nullable()->comment('The type of the modifiable model');
            $table->unsignedBigInteger('modifier_id')->nullable()->comment('The id of the modifier model');
            $table->string('modifier_type')->nullable()->comment('The type of the modifier model');
            $table->boolean('active')->default(true)->comment('Whether the modification is active');
            $table->boolean('is_update')->default(true)->comment('Whether the modification is an update');
            $table->unsignedInteger('approvers_required')->default(1)->comment('The number of approvers required');
            $table->unsignedInteger('disapprovers_required')->default(1)->comment('The number of disapprovers required');
            $table->string('md5')->comment('The md5 hash of the modifications');
            $table->json('modifications')->comment('The modifications');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true
            );

            $table->index(['modifier_id', 'modifier_type'], 'modifications_modifierable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('modifications');
    }
};
