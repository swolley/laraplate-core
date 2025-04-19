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
        Schema::create('disapprovals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('modification_id')->comment('The id of the modification');
            $table->unsignedBigInteger('disapprover_id')->comment('The id of the disapprover');
            $table->string('disapprover_type')->comment('The type of the disapprover');
            $table->text('reason')->nullable()->comment('The reason for the disapproval');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true
            );

            $table->foreign(['modification_id'])->references('id')->on('modifications')->cascadeOnDelete();
            $table->index(['disapprover_id', 'disapprover_type'], 'disapprovals_disapproverable_IDX');
        });
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists('disapprovals');
    }
};
