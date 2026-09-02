<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CoreTables;

/**
 * The permissions table is filled by `permission:refresh`, which used to name the
 * resolved connection (`sqlite` here, `mysql` in production) instead of `default`.
 */
function insertLegacyPermission(string $name): int
{
    return (int) DB::table(CoreTables::Permissions->value)->insertGetId([
        'name' => $name,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function runPermissionNameNormalization(): void
{
    $migration = require module_path('Core', 'database/migrations/2026_09_02_000000_normalize_default_connection_permission_names.php');

    $migration->up();
}

it('renames a resolved-connection permission onto the default prefix', function (): void {
    $resolved = (string) config('database.default');
    $legacy_id = insertLegacyPermission("{$resolved}.migration_widgets.select");

    runPermissionNameNormalization();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $legacy_id)->value('name'))
        ->toBe('default.migration_widgets.select');
});

it('merges a legacy permission onto the default one it duplicates, keeping the grants', function (): void {
    $resolved = (string) config('database.default');
    $role_id = DB::table(CoreTables::Roles->value)->insertGetId([
        'name' => 'migration_merge_role',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $survivor_id = insertLegacyPermission('default.migration_gadgets.select');
    $legacy_id = insertLegacyPermission("{$resolved}.migration_gadgets.select");

    DB::table(CoreTables::RoleHasPermissions->value)->insert([
        'permission_id' => $legacy_id,
        'role_id' => $role_id,
    ]);

    $acl_id = DB::table(CoreTables::Acls->value)->insertGetId([
        'permission_id' => $legacy_id,
        'role_id' => $role_id,
        'unrestricted' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runPermissionNameNormalization();

    expect(DB::table(CoreTables::Permissions->value)->where('id', $legacy_id)->exists())->toBeFalse()
        ->and(DB::table(CoreTables::Permissions->value)->where('id', $survivor_id)->value('name'))
        ->toBe('default.migration_gadgets.select')
        ->and(DB::table(CoreTables::RoleHasPermissions->value)
            ->where('permission_id', $survivor_id)
            ->where('role_id', $role_id)
            ->exists())->toBeTrue()
        ->and(DB::table(CoreTables::Acls->value)->where('id', $acl_id)->value('permission_id'))
        ->toBe($survivor_id);
});

it('drops a duplicate grant instead of colliding with the one the survivor already holds', function (): void {
    $resolved = (string) config('database.default');
    $role_id = DB::table(CoreTables::Roles->value)->insertGetId([
        'name' => 'migration_collision_role',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $survivor_id = insertLegacyPermission('default.migration_bolts.update');
    $legacy_id = insertLegacyPermission("{$resolved}.migration_bolts.update");

    DB::table(CoreTables::RoleHasPermissions->value)->insert([
        ['permission_id' => $survivor_id, 'role_id' => $role_id],
        ['permission_id' => $legacy_id, 'role_id' => $role_id],
    ]);

    runPermissionNameNormalization();

    expect(DB::table(CoreTables::RoleHasPermissions->value)->where('role_id', $role_id)->count())->toBe(1)
        ->and(DB::table(CoreTables::RoleHasPermissions->value)
            ->where('permission_id', $survivor_id)
            ->where('role_id', $role_id)
            ->exists())->toBeTrue();
});
