<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = app('db')->connection();
        $schema = $connection->getSchemaBuilder();
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (! $teams) {
            return;
        }

        if ($tableNames === '' || $tableNames === null) {
            throw new RuntimeException('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        if (($columnNames['team_foreign_key'] ?? null) === null) {
            throw new RuntimeException('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        $roles_table = $tableNames['roles'];
        $permissions_table = $tableNames['permissions'];
        $model_has_permissions_table = $tableNames['model_has_permissions'];
        $model_has_roles_table = $tableNames['model_has_roles'];

        if (! $schema->hasColumn($roles_table, $columnNames['team_foreign_key'])) {
            $schema->table($roles_table, function (Blueprint $table) use ($columnNames, $roles_table): void {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable()->after('id')->default('1')->comment('The team id');
                $table->index($columnNames['team_foreign_key'], "{$roles_table}_team_foreign_key_index");

                $table->dropUnique("{$roles_table}_name_guard_name_unique");
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            });
        }

        if (! $schema->hasColumn($model_has_permissions_table, $columnNames['team_foreign_key'])) {
            $schema->table($model_has_permissions_table, function (Blueprint $table) use ($connection, $model_has_permissions_table, $columnNames, $pivotPermission, $permissions_table): void {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->default('1')->comment('The team id');
                $table->index($columnNames['team_foreign_key'], "{$model_has_permissions_table}_team_foreign_key_index");

                if ($connection->getDriverName() !== 'sqlite') {
                    $table->dropForeign([$pivotPermission]);
                }
                $table->dropPrimary();

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    "{$model_has_permissions_table}_permission_model_type_primary",
                );

                if ($connection->getDriverName() !== 'sqlite') {
                    $table->foreign($pivotPermission)
                        ->references('id')->on($permissions_table)->onDelete('cascade');
                }
            });
        }

        if (! $schema->hasColumn($model_has_roles_table, $columnNames['team_foreign_key'])) {
            $schema->table($model_has_roles_table, function (Blueprint $table) use ($connection, $model_has_roles_table, $columnNames, $pivotRole, $roles_table): void {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->default('1')->comment('The team id');
                $table->index($columnNames['team_foreign_key'], "{$model_has_roles_table}_team_foreign_key_index");

                if ($connection->getDriverName() !== 'sqlite') {
                    $table->dropForeign([$pivotRole]);
                }
                $table->dropPrimary();

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    "{$model_has_roles_table}_role_model_type_primary",
                );

                if ($connection->getDriverName() !== 'sqlite') {
                    $table->foreign($pivotRole)
                        ->references('id')->on($roles_table)->onDelete('cascade');
                }
            });
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
