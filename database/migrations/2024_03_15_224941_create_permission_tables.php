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
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        if ($teams && empty($columnNames['team_foreign_key'] ?? null)) {
            throw new Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        Schema::create($tableNames['permissions'], function (Blueprint $table): void {
            $table->bigIncrements('id'); // permission id
            $table->string('name', 125)->nullable(false)->comment('The name of the permission');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name', 125)->default('web')->nullable(false)->comment('The guard name of the permission'); // For MySQL 8.0 use string('guard_name', 125);
            $table->string('description')->after('guard_name')->nullable(true)->comment('The description of the permission');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['name', 'guard_name'], 'permissions_UN');
        });

        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE permissions ADD COLUMN connection_name VARCHAR(50) GENERATED ALWAYS AS (regexp_replace(regexp_replace(name, '\\.\\w+\\.\\w+$', ''), '\\.', '')) STORED");
            DB::statement("ALTER TABLE permissions ADD COLUMN table_name VARCHAR(50) GENERATED ALWAYS AS (regexp_replace(regexp_replace(name, '^\\w+\\.', ''), '\\.\\w+$', '')) STORED");
            DB::statement('CREATE INDEX permissions_ref_IDX ON permissions (connection_name, table_name)');
            DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_name_CHECK CHECK (name ~ '^\\w+\\.\\w+\\.\\w+$')");
        } elseif (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE permissions ADD COLUMN connection_name VARCHAR(50) AS (regexp_substr(name, '^\\\\w+')) STORED");
            DB::statement("ALTER TABLE permissions ADD COLUMN table_name VARCHAR(50) AS (replace(regexp_substr(name, '\\\\.\\\\w+\\\\.'), '.', '')) STORED");
            DB::statement('CREATE INDEX permissions_ref_IDX ON permissions (connection_name, table_name)');
            DB::statement("ALTER TABLE permissions ADD CONSTRAINT permissions_name_CHECK CHECK (REGEXP_INSTR(name, '^\\\\w+\\\\.\\\\w+\\\\.\\\\w+$') = 1)");
        } elseif ($connection->getDriverName() === 'sqlite') {
            DB::statement("ALTER TABLE permissions ADD COLUMN connection_name TEXT AS (regexp_replace(regexp_replace(name, '\\.\\w+\\.\\w+$', ''), '\\.', '')) STORED");
            DB::statement("ALTER TABLE permissions ADD COLUMN table_name TEXT AS (regexp_replace(regexp_replace(name, '^\\w+\\.', ''), '\\.\\w+$', '')) STORED");
            DB::statement('CREATE INDEX permissions_ref_IDX ON permissions (connection_name, table_name)');
        } else {
            throw new Exception('Unsupported database driver');
        }

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames): void {
            $table->bigIncrements('id'); // role id

            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable()->comment('The team foreign key of the role');
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name', 125)->nullable(false)->comment('The name of the role');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name', 125)->default('web')->nullable(false)->comment('The guard name of the role'); // For MySQL 8.0 use string('guard_name', 125);
            $table->string('description')->after('guard_name')->nullable(true)->comment('The description of the role');
            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
            );

            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name'], 'roles_UN');
            } else {
                $table->unique(['name', 'guard_name'], 'roles_UN');
            }
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($tableNames): void {
            $table->unsignedBigInteger('parent_id')->nullable()->comment('The parent id of the role');
            $table->foreign('parent_id', 'parent_role_FK')->references('id')->on($tableNames['roles'])->nullOnDelete();
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams): void {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type')->nullable(false)->comment('The model type of the permission');
            $table->unsignedBigInteger($columnNames['model_morph_key'])->nullable(false)->comment('The model id of the permission');
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_morph_idx');

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable(false)->comment('The team foreign key of the permission');
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_idx');

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary',
                );
            } else {
                $table->primary(
                    [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary',
                );
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams): void {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type')->nullable(false)->comment('The model type of the role');
            $table->unsignedBigInteger($columnNames['model_morph_key'])->nullable(false)->comment('The model id of the role');
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_morph_idx');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable(false)->comment('The team foreign key of the role');
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_idx');

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary',
                );
            } else {
                $table->primary(
                    [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary',
                );
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission): void {
            $table->unsignedBigInteger($pivotPermission)->nullable(false)->comment('The permission id of the role');
            $table->unsignedBigInteger($pivotRole)->nullable(false)->comment('The role id of the permission');

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
