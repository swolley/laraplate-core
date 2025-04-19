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
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('modification_id')->comment('The id of the modification');
            $table->unsignedBigInteger('approver_id')->comment('The id of the approver');
            $table->string('approver_type')->comment('The type of the approver');
            $table->text('reason')->nullable()->comment('The reason for the approval');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true
            );

            $table->foreign(['modification_id'])->references('id')->on('modifications')->cascadeOnDelete();
            $table->index(['approver_id', 'approver_type'], 'approvals_approverable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
