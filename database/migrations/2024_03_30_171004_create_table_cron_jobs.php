<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable(false)->comment('The name of the cron job');
            $table->string('command')->nullable(false)->comment('The command of the cron job');
            $table->json('parameters')->nullable(false)->comment('The parameters of the cron job');
            $table->string('schedule')->nullable(false)->comment('The schedule of the cron job');
            $table->boolean('is_active')->nullable(false)->index('cron_jobs_is_active_IDX')->comment('Is the cron job active');
            $table->string('description')->nullable()->comment('The description of the cron job');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
