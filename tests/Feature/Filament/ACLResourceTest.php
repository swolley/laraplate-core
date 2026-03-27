<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

it('defines Filament pages for acls', function (): void {
    $pages = ACLResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('acl resource has required form fields', function (): void {
    $schema = ACLResource::form(new Schema());
    $components = $schema->getComponents();

    $names = array_map(static fn ($c): ?string => method_exists($c, 'getName') ? $c->getName() : null, $components);

    expect($names)->toContain('permission_id')
        ->and($names)->toContain('filters')
        ->and($names)->toContain('sort')
        ->and($names)->toContain('description');
});

it('acl resource has required table columns', function (): void {
    test()->markTestSkipped('Table column configuration is exercised at app level with full Filament panel wiring.');
});

it('acl resource has required actions', function (): void {
    test()->markTestSkipped('Table actions configuration is exercised at app level with full Filament panel wiring.');
});
