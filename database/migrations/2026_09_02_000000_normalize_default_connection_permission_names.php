<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Spatie\Permission\PermissionRegistrar;

/**
 * Collapse `{database.default}.…` permissions onto the `default.…` convention.
 *
 * Permission names are `{connection}.{table}.{operation}`, and a model that does
 * not pin a connection is named with the literal `default` segment: the resolved
 * driver changes per environment, so baking it into the name makes the very same
 * permission unmatchable elsewhere. `permission:refresh` used to write the
 * resolved name instead, so an installation running on MySQL accumulated
 * `mysql.contents.select` next to the `default.contents.select` that the module
 * seeders, the policies and every runtime check produce.
 *
 * Legacy rows are renamed; where both spellings exist the role/model assignments
 * and the ACLs are repointed at the surviving row before the legacy one is dropped.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var ConnectionInterface $connection */
        $connection = app('db')->connection();

        $default_connection = (string) config('database.default');

        if ($default_connection === '' || $default_connection === 'default') {
            return;
        }

        $permissions_table = (string) config('permission.table_names.permissions', CoreTables::Permissions->value);

        if (! Schema::hasTable($permissions_table) || ! Schema::hasColumn($permissions_table, 'connection_name')) {
            return;
        }

        $legacy_rows = $connection->table($permissions_table)
            ->where('connection_name', $default_connection)
            ->get(['id', 'name', 'guard_name']);

        if ($legacy_rows->isEmpty()) {
            return;
        }

        $prefix_length = mb_strlen($default_connection) + 1;

        foreach ($legacy_rows as $legacy) {
            $target_name = 'default.' . mb_substr((string) $legacy->name, $prefix_length);

            $target_id = $connection->table($permissions_table)
                ->where('name', $target_name)
                ->where('guard_name', $legacy->guard_name)
                ->value('id');

            if ($target_id === null) {
                $connection->table($permissions_table)
                    ->where('id', $legacy->id)
                    ->update(['name' => $target_name]);

                continue;
            }

            $this->mergeAssignments($connection, (int) $legacy->id, (int) $target_id);

            $connection->table($permissions_table)->where('id', $legacy->id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: assignments merged onto the surviving permission cannot be
        // split back into the two spellings they came from.
    }

    /**
     * Move every grant on the legacy permission onto the surviving one.
     */
    private function mergeAssignments(ConnectionInterface $connection, int $legacy_id, int $target_id): void
    {
        $permission_key = (string) (config('permission.column_names.permission_pivot_key') ?: 'permission_id');

        $pivot_tables = [
            (string) config('permission.table_names.role_has_permissions', CoreTables::RoleHasPermissions->value),
            (string) config('permission.table_names.model_has_permissions', CoreTables::ModelHasPermissions->value),
        ];

        foreach ($pivot_tables as $pivot_table) {
            if (! Schema::hasTable($pivot_table)) {
                continue;
            }

            $this->repointPivot($connection, $pivot_table, $permission_key, $legacy_id, $target_id);
        }

        $acls_table = CoreTables::Acls->value;

        if (Schema::hasTable($acls_table)) {
            $connection->table($acls_table)
                ->where('permission_id', $legacy_id)
                ->update(['permission_id' => $target_id]);
        }
    }

    /**
     * The pivots carry a composite key (role, or model type + id, plus the team key
     * when teams are on), so a grant is moved only when the survivor does not hold
     * the same one already; otherwise the legacy row is simply dropped.
     */
    private function repointPivot(
        ConnectionInterface $connection,
        string $pivot_table,
        string $permission_key,
        int $legacy_id,
        int $target_id,
    ): void {
        $rows = $connection->table($pivot_table)->where($permission_key, $legacy_id)->get();

        foreach ($rows as $row) {
            /** @var array<string,mixed> $holder */
            $holder = (array) $row;
            unset($holder[$permission_key]);

            $legacy_row = $connection->table($pivot_table)->where($permission_key, $legacy_id);
            $survivor_row = $connection->table($pivot_table)->where($permission_key, $target_id);

            foreach ($holder as $column => $value) {
                $legacy_row->where($column, $value);
                $survivor_row->where($column, $value);
            }

            if ($survivor_row->exists()) {
                $legacy_row->delete();

                continue;
            }

            $legacy_row->update([$permission_key => $target_id]);
        }
    }
};
