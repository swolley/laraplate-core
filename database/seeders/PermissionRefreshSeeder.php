<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Overrides\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Graph node wrapping `permission:refresh`.
 *
 * Split out of {@see CoreDatabaseSeeder::defaultPermissions()} so any seeder that needs a
 * freshly-inspected permission set (e.g. `ERPDatabaseSeeder::ensureDomainPermissions()`) can
 * declare a dependency on it instead of relying on undeclared run order. `CoreDatabaseSeeder`
 * itself depends on this node because `defaultRoles()` assigns permissions that only exist once
 * `permission:refresh` has run.
 */
final class PermissionRefreshSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->logOperation((string) config('permission.models.permission'));
        Artisan::call('permission:refresh');
        $this->command?->line('    - permissions updated');
    }
}
