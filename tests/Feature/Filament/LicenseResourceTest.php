<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $admin->roles()->attach($adminRole);

    $this->actingAs($admin);
});

it('defines Filament pages for licenses', function (): void {
    $pages = LicenseResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('license resource has required form fields', function (): void {
    $schema = LicenseResource::form(new Schema());
    $components = $schema->getComponents();

    $names = array_map(static fn ($c): ?string => method_exists($c, 'getName') ? $c->getName() : null, $components);

    expect($names)->toContain('name')
        ->and($names)->toContain('key')
        ->and($names)->toContain('domain')
        ->and($names)->toContain('email')
        ->and($names)->toContain('company')
        ->and($names)->toContain('expires_at')
        ->and($names)->toContain('notes');
});

it('license resource has required table columns', function (): void {
    test()->markTestSkipped('Table column configuration is exercised at app level with full Filament panel wiring.');
});

it('license resource has required actions', function (): void {
    test()->markTestSkipped('Table actions configuration is exercised at app level with full Filament panel wiring.');
});
