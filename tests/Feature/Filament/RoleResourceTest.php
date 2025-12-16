<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

test('can list roles', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.roles.index'));

    $response->assertSuccessful();
});

test('can create role', function (): void {
    /** @var TestCase $this */
    $roleData = [
        'name' => 'Test Role',
        'description' => 'Test role description',
    ];

    $response = $this->actingAs($this->admin)
        ->post(route('filament.admin.resources.core.roles.create'), $roleData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('roles')->where([
        'name' => 'Test Role',
        'description' => 'Test role description',
    ])->exists())->toBeTrue();
});

test('can edit role', function (): void {
    /** @var TestCase $this */
    $role = Role::factory()->create();

    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.roles.edit', ['record' => $role]));

    $response->assertSuccessful();
});

test('can update role', function (): void {
    /** @var TestCase $this */
    $role = Role::factory()->create();
    $updateData = [
        'name' => 'Updated Role',
        'description' => 'Updated role description',
    ];

    $response = $this->actingAs($this->admin)
        ->put(route('filament.admin.resources.core.roles.update', ['record' => $role]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('roles')->where([
        'id' => $role->id,
        'name' => 'Updated Role',
        'description' => 'Updated role description',
    ])->exists())->toBeTrue();
});

test('can delete role', function (): void {
    /** @var TestCase $this */
    $role = Role::factory()->create();

    $response = $this->actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.roles.delete', ['record' => $role]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('roles')->where('id', $role->id)->exists())->toBeFalse();
});

test('role resource has required form fields', function (): void {
    $resource = new Modules\Core\Filament\Resources\Roles\RoleResource();
    $form = $resource->form(new Filament\Schemas\Schema());

    expect($form->hasComponent('name', 'text'))->toBeTrue();
    expect($form->hasComponent('description', 'textarea'))->toBeTrue();
    expect($form->hasComponent('permissions', 'select'))->toBeTrue();
});

test('role resource has required table columns', function (): void {
    $resource = new Modules\Core\Filament\Resources\Roles\RoleResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('name', 'text'))->toBeTrue();
    expect($table->hasColumn('description', 'text'))->toBeTrue();
    expect($table->hasColumn('permissions.name', 'text'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('role resource has required actions', function (): void {
    $resource = new Modules\Core\Filament\Resources\Roles\RoleResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getRecordActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
});
