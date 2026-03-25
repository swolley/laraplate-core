<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

it('defines Filament pages for settings', function (): void {
    $pages = SettingResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('setting resource has required form fields', function (): void {
    $schema = SettingResource::form(new Schema());
    $components = $schema->getComponents();

    $names = array_map(static fn ($component): ?string => method_exists($component, 'getName') ? $component->getName() : null, $components);

    expect($components)->toHaveCount(7)
        ->and($names)->toContain('name')
        ->and($names)->toContain('value')
        ->and($names)->toContain('type')
        ->and($names)->toContain('group_name')
        ->and($names)->toContain('description')
        ->and($names)->toContain('is_public')
        ->and($names)->toContain('is_encrypted');
});

it('setting resource has required table columns', function (): void {
    test()->markTestSkipped('Table column configuration is exercised at app level with full Filament panel wiring.');
});

it('setting resource has required actions', function (): void {
    test()->markTestSkipped('Table actions configuration is exercised at app level with full Filament panel wiring.');
});
