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
        $disapprovals_table = CoreTables::Disapprovals->value;
        Schema::create($disapprovals_table, function (Blueprint $table) use ($disapprovals_table): void {
            $table->id();
            $table->unsignedBigInteger('modification_id')->comment('The id of the modification');
            $table->unsignedBigInteger('disapprover_id')->comment('The id of the disapprover');
            $table->string('disapprover_type')->comment('The type of the disapprover');
            $table->text('reason')->nullable()->comment('The reason for the disapproval');
            $table->json('meta')->nullable()->comment('The additional meta data for the disapproval');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );

            $table->foreign(['modification_id'])->references('id')->on(CoreTables::Modifications->value)->cascadeOnDelete();
            $table->index(['disapprover_id', 'disapprover_type'], "{$disapprovals_table}_disapproverable_IDX");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CoreTables::Disapprovals->value);
    }
};
