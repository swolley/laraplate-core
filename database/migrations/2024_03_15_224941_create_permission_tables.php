<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\MigrateUtils;

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

        $permissions_table = $tableNames['permissions'];
        $roles_table = $tableNames['roles'];
        $model_has_permissions_table = $tableNames['model_has_permissions'];
        $model_has_roles_table = $tableNames['model_has_roles'];
        $role_has_permissions_table = $tableNames['role_has_permissions'];

        if ($tableNames === '' || $tableNames === null) {
            throw new RuntimeException('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        if ($teams && ($columnNames['team_foreign_key'] ?? null) === null) {
            throw new RuntimeException('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        Schema::create($permissions_table, function (Blueprint $table) use ($permissions_table): void {
            $table->bigIncrements('id'); // permission id
            $table->string('name', 125)->nullable(false)->comment('The name of the permission');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name', 125)->default('web')->nullable(false)->comment('The guard name of the permission'); // For MySQL 8.0 use string('guard_name', 125);
            $table->string('description')->nullable(true)->comment('The description of the permission');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
            );

            $table->unique(['name', 'guard_name'], "{$permissions_table}_UN");
        });

        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$permissions_table} ADD COLUMN connection_name VARCHAR(50) GENERATED ALWAYS AS (regexp_replace(regexp_replace(name, '\\.\\w+\\.\\w+$', ''), '\\.', '')) STORED");
            DB::statement("ALTER TABLE {$permissions_table} ADD COLUMN table_name VARCHAR(50) GENERATED ALWAYS AS (regexp_replace(regexp_replace(name, '^\\w+\\.', ''), '\\.\\w+$', '')) STORED");
            DB::statement("CREATE INDEX {$permissions_table}_ref_IDX ON {$permissions_table} (connection_name, table_name)");
            DB::statement("ALTER TABLE {$permissions_table} ADD CONSTRAINT {$permissions_table}_name_CHECK CHECK (name ~ '^\\w+\\.\\w+\\.\\w+$')");
        } elseif (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE {$permissions_table} ADD COLUMN connection_name VARCHAR(50) AS (regexp_substr(name, '^\\\\w+')) STORED");
            DB::statement("ALTER TABLE {$permissions_table} ADD COLUMN table_name VARCHAR(50) AS (replace(regexp_substr(name, '\\\\.\\\\w+\\\\.'), '.', '')) STORED");
            DB::statement("CREATE INDEX {$permissions_table}_ref_IDX ON {$permissions_table} (connection_name, table_name)");
            DB::statement("ALTER TABLE {$permissions_table} ADD CONSTRAINT {$permissions_table}_name_CHECK CHECK (REGEXP_INSTR(name, '^\\\\w+\\\\.\\\\w+\\\\.\\\\w+$') = 1)");
        } elseif ($connection->getDriverName() === 'sqlite') {
            // SQLite doesn't support regexp_replace in generated columns, so we use regular columns with triggers
            DB::statement("ALTER TABLE {$permissions_table} ADD COLUMN connection_name TEXT");
            DB::statement("ALTER TABLE {$permissions_table} ADD COLUMN table_name TEXT");
            DB::statement("CREATE INDEX {$permissions_table}_ref_IDX ON {$permissions_table} (connection_name, table_name)");

            // Trigger to extract connection_name (first part before first dot, removing dots if multiple)
            DB::statement("
                CREATE TRIGGER permissions_set_connection_name 
                AFTER INSERT ON {$permissions_table}
                BEGIN
                    UPDATE {$permissions_table} 
                    SET connection_name = substr(name, 1, CASE 
                        WHEN instr(substr(name, instr(name, '.') + 1), '.') > 0 
                        THEN instr(name, '.') - 1 
                        ELSE length(name) 
                    END)
                    WHERE id = NEW.id;
                END
            ");

            DB::statement("
                CREATE TRIGGER permissions_update_connection_name 
                AFTER UPDATE OF name ON {$permissions_table}
                BEGIN
                    UPDATE {$permissions_table} 
                    SET connection_name = substr(name, 1, CASE 
                        WHEN instr(substr(name, instr(name, '.') + 1), '.') > 0 
                        THEN instr(name, '.') - 1 
                        ELSE length(name) 
                    END)
                    WHERE id = NEW.id;
                END
            ");

            // Trigger to extract table_name (middle part between first and last dot)
            DB::statement("
                CREATE TRIGGER permissions_set_table_name 
                AFTER INSERT ON {$permissions_table}
                BEGIN
                    UPDATE {$permissions_table} 
                    SET table_name = substr(
                        name, 
                        instr(name, '.') + 1,
                        CASE 
                            WHEN instr(substr(name, instr(name, '.') + 1), '.') > 0 
                            THEN instr(substr(name, instr(name, '.') + 1), '.') - 1
                            ELSE 0
                        END
                    )
                    WHERE id = NEW.id;
                END
            ");

            DB::statement("
                CREATE TRIGGER permissions_update_table_name 
                AFTER UPDATE OF name ON {$permissions_table}
                BEGIN
                    UPDATE {$permissions_table} 
                    SET table_name = substr(
                        name, 
                        instr(name, '.') + 1,
                        CASE 
                            WHEN instr(substr(name, instr(name, '.') + 1), '.') > 0 
                            THEN instr(substr(name, instr(name, '.') + 1), '.') - 1
                            ELSE 0
                        END
                    )
                    WHERE id = NEW.id;
                END
            ");
        } else {
            throw new RuntimeException('Unsupported database driver');
        }

        Schema::create($roles_table, function (Blueprint $table) use ($teams, $columnNames, $roles_table): void {
            $table->bigIncrements('id'); // role id

            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable()->comment('The team foreign key of the role');
                $table->index($columnNames['team_foreign_key'], "{$roles_table}_team_foreign_key_index");
            }
            $table->string('name', 125)->nullable(false)->comment('The name of the role');       // For MySQL 8.0 use string('name', 125);
            $table->string('guard_name', 125)->default('web')->nullable(false)->comment('The guard name of the role'); // For MySQL 8.0 use string('guard_name', 125);
            $table->string('description')->nullable(true)->comment('The description of the role');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
                hasSoftDelete: true,
                hasLocks: true,
            );

            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name'], "{$roles_table}_UN");
            } else {
                $table->unique(['name', 'guard_name'], "{$roles_table}_UN");
            }
        });

        Schema::table($roles_table, function (Blueprint $table) use ($roles_table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->comment('The parent id of the role');
            $table->foreign('parent_id', "{$roles_table}_parent_role_FK")->references('id')->on($roles_table)->nullOnDelete();
        });

        Schema::create($model_has_permissions_table, function (Blueprint $table) use ($model_has_permissions_table, $columnNames, $pivotPermission, $teams, $permissions_table): void {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type')->nullable(false)->comment('The model type of the permission');
            $table->unsignedBigInteger($columnNames['model_morph_key'])->nullable(false)->comment('The model id of the permission');
            $table->index([$columnNames['model_morph_key'], 'model_type'], "{$model_has_permissions_table}_morph_idx");

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($permissions_table)
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable(false)->comment('The team foreign key of the permission');
                $table->index($columnNames['team_foreign_key'], "{$model_has_permissions_table}_team_idx");

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    "{$model_has_permissions_table}_permission_model_type_primary",
                );
            } else {
                $table->primary(
                    [$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    "{$model_has_permissions_table}_permission_model_type_primary",
                );
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        Schema::create($model_has_roles_table, function (Blueprint $table) use ($model_has_roles_table, $columnNames, $pivotRole, $teams, $roles_table): void {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type')->nullable(false)->comment('The model type of the role');
            $table->unsignedBigInteger($columnNames['model_morph_key'])->nullable(false)->comment('The model id of the role');
            $table->index([$columnNames['model_morph_key'], 'model_type'], "{$model_has_roles_table}_morph_idx");

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($roles_table)
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable(false)->comment('The team foreign key of the role');
                $table->index($columnNames['team_foreign_key'], "{$model_has_roles_table}_team_idx");

                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    "{$model_has_roles_table}_role_model_type_primary",
                );
            } else {
                $table->primary(
                    [$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    "{$model_has_roles_table}_role_model_type_primary",
                );
            }

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );
        });

        Schema::create($role_has_permissions_table, function (Blueprint $table) use ($role_has_permissions_table, $pivotRole, $pivotPermission, $permissions_table, $roles_table): void {
            $table->unsignedBigInteger($pivotPermission)->nullable(false)->comment('The permission id of the role');
            $table->unsignedBigInteger($pivotRole)->nullable(false)->comment('The role id of the permission');

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($permissions_table)
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($roles_table)
                ->onDelete('cascade');

            MigrateUtils::timestamps(
                $table,
                hasCreateUpdate: true,
            );

            $table->primary([$pivotPermission, $pivotRole], "{$role_has_permissions_table}_primary");
        });

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if ($tableNames === '' || $tableNames === null) {
            throw new RuntimeException('Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
