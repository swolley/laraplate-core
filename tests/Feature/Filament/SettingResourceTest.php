<?php

declare(strict_types=1);

use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

it('defines Filament pages for settings', function (): void {
    $pages = SettingResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('edit')
        ->and($pages)->not->toHaveKey('create');
});

it('does not allow creating settings from the panel', function (): void {
    expect(SettingResource::canCreate())->toBeFalse();
});

it('setting resource has required table columns', function (): void {
    test()->markTestSkipped('Table column configuration is exercised at app level with full Filament panel wiring.');
});

it('setting resource has required actions', function (): void {
    test()->markTestSkipped('Table actions configuration is exercised at app level with full Filament panel wiring.');
});
