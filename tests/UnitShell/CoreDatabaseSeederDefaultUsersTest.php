<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

uses(Tests\TestCase::class);

it('skips every default user username when checking existing records', function (): void {
    $source = file_get_contents((new ReflectionClass(CoreDatabaseSeeder::class))->getFileName());

    expect($source)->toContain('array_column($users_data, \'username\')')
        ->and($source)->not->toContain('whereIn(\'username\', [$anonymous, $superadmin, $admin])');
});

it('seeds clearUserAssignedLicenses is_active from the runtime license setting', function (): void {
    $source = file_get_contents((new ReflectionClass(CoreDatabaseSeeder::class))->getFileName());

    expect($source)->toContain('(bool) config(\'core.enable_user_licenses\', false)')
        ->and($source)->not->toContain('config(\'auth.enable_user_licenses\')');
});
