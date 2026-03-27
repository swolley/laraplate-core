<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Filament\Resources\Users\UserResource;
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

it('defines Filament pages for users', function (): void {
    $pages = UserResource::getPages();

    expect($pages)
        ->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit');
});

it('user resource has required form fields', function (): void {
    test()->markTestSkipped(
        'UserForm uses relationship() and other features that require Schema to be bound to a Livewire component; '
        . 'form structure is exercised at app level with full Filament panel wiring.',
    );
});

it('user resource has required table columns', function (): void {
    test()->markTestSkipped('Table column configuration is exercised at app level with full Filament panel wiring.');
});

it('user resource has required actions', function (): void {
    test()->markTestSkipped('Table actions configuration is exercised at app level with full Filament panel wiring.');
});
