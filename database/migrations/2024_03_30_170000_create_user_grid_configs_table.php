<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Modules\Core\Helpers\CommonMigrationFunctions;

return new class() extends Migration
{
    private string $table_name = 'user_grid_configs';

    /**
     * Run the migrations.
     *
     */
    public function up(): void
    {
        Schema::create($this->table_name, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(true);
            $table->string('grid_name')->nullable(false);
            $table->string('layout_name')->nullable(false);
            $table->boolean('is_public')->default(false)->index('user_grid_configs_is_public_IDX');
            $table->json('config');
            CommonMigrationFunctions::timestamps(
                $table,
                hasCreateUpdate: true
            );

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'grid_name', 'layout_name']);
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $true = 'TRUE';
        } elseif (in_array($driver, ['mysql', 'mariadb', 'sqlite'])) {
            $true = 1;
        } else {
            throw new \Exception('Unsupported database driver');
        }

        if ($driver !== 'sqlite') {
            DB::statement(
                "ALTER TABLE {$this->table_name}
                ADD CONSTRAINT {$this->table_name}_public_CHECK 
                CHECK (is_public = {$true} OR user_id IS NOT NULL);",
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     */
    public function down(): void
    {
        Schema::dropIfExists($this->table_name);
    }
};
