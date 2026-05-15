<?php

declare(strict_types=1);

uses(Modules\Core\Tests\ApplicationTestCase::class);

it('resolves module from unix migration path via file_module', function (): void {
    $path = module_path('AI', 'database/migrations/2026_01_24_174744_create_ai_messages_table.php');

    expect(file_module($path))->toBe('AI');
});

it('resolves app when path is outside modules', function (): void {
    expect(file_module(database_path('migrations/0001_01_01_000000_create_users_table.php')))->toBe('App');
});
