<?php

declare(strict_types=1);

use Modules\Core\Helpers\MigrationModuleResolver;

uses(Modules\Core\Tests\ApplicationTestCase::class);

it('resolves module from migration file path', function (): void {
    $path = module_path('Core', 'database/migrations/2024_11_28_224400_create_presettables_table.php');

    expect(MigrationModuleResolver::resolveFromPath($path))->toBe('Core');
});

it('resolves module from migration name', function (): void {
    expect(MigrationModuleResolver::resolveFromName('2024_11_28_224400_create_presettables_table'))->toBe('Core');
});

it('resolves app migrations as App', function (): void {
    $path = database_path('migrations/0001_01_01_000000_create_users_table.php');

    expect(MigrationModuleResolver::resolveFromPath($path))->toBe('App');
});
