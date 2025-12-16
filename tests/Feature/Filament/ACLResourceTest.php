<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
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

test('can list acls', function (): void {
    /** @var TestCase $this */
    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.acls.index'));

    $response->assertSuccessful();
});

test('can create acl', function (): void {
    /** @var TestCase $this */
    $permission = Permission::create(['name' => 'default.test_table.select']);
    $aclData = [
        'permission_id' => $permission->id,
        'filters' => json_encode(['filters' => [['field' => 'test', 'value' => 'value']], 'operator' => 'and']),
        'sort' => json_encode([['property' => 'created_at', 'direction' => 'desc']]),
        'description' => 'Test ACL description',
    ];

    $response = $this->actingAs($this->admin)
        ->post(route('filament.admin.resources.core.acls.create'), $aclData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('acls')->where([
        'permission_id' => $permission->id,
        'description' => 'Test ACL description',
    ])->exists())->toBeTrue();
});

test('can edit acl', function (): void {
    /** @var TestCase $this */
    $permission = Permission::create(['name' => 'default.test_table.select']);
    $acl = ACL::create([
        'permission_id' => $permission->id,
        'filters' => json_encode(['filters' => [], 'operator' => 'and']),
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('filament.admin.resources.core.acls.edit', ['record' => $acl]));

    $response->assertSuccessful();
});

test('can update acl', function (): void {
    /** @var TestCase $this */
    $permission1 = Permission::create(['name' => 'default.test_table.select']);
    $acl = ACL::create([
        'permission_id' => $permission1->id,
        'filters' => json_encode(['filters' => [], 'operator' => 'and']),
    ]);
    $permission2 = Permission::create(['name' => 'default.updated_table.select']);
    $updateData = [
        'permission_id' => $permission2->id,
        'filters' => json_encode(['filters' => [['field' => 'updated', 'value' => 'value']], 'operator' => 'and']),
        'sort' => json_encode([['property' => 'updated_at', 'direction' => 'asc']]),
        'description' => 'Updated ACL description',
    ];

    $response = $this->actingAs($this->admin)
        ->put(route('filament.admin.resources.core.acls.update', ['record' => $acl]), $updateData);

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('acls')->where([
        'id' => $acl->id,
        'permission_id' => $permission2->id,
        'description' => 'Updated ACL description',
    ])->exists())->toBeTrue();
});

test('can delete acl', function (): void {
    /** @var TestCase $this */
    $permission = Permission::create(['name' => 'default.test_table.select']);
    $acl = ACL::create([
        'permission_id' => $permission->id,
        'filters' => json_encode(['filters' => [], 'operator' => 'and']),
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('filament.admin.resources.core.acls.delete', ['record' => $acl]));

    $response->assertSuccessful();
    expect(Illuminate\Support\Facades\DB::table('acls')->where('id', $acl->id)->exists())->toBeFalse();
});

test('acl resource has required form fields', function (): void {
    $resource = new Modules\Core\Filament\Resources\ACLS\ACLResource();
    $form = $resource->form(new Filament\Schemas\Schema());

    expect($form->hasComponent('permission_id', 'select'))->toBeTrue();
    expect($form->hasComponent('filters', 'json'))->toBeTrue();
    expect($form->hasComponent('sort', 'json'))->toBeTrue();
    expect($form->hasComponent('description', 'textarea'))->toBeTrue();
});

test('acl resource has required table columns', function (): void {
    $resource = new Modules\Core\Filament\Resources\ACLS\ACLResource();
    $table = $resource->table(new Filament\Tables\Table());

    expect($table->hasColumn('permission.name', 'text'))->toBeTrue();
    expect($table->hasColumn('filters', 'json'))->toBeTrue();
    expect($table->hasColumn('sort', 'json'))->toBeTrue();
    expect($table->hasColumn('description', 'text'))->toBeTrue();
    expect($table->hasColumn('created_at', 'date'))->toBeTrue();
});

test('acl resource has required actions', function (): void {
    $resource = new Modules\Core\Filament\Resources\ACLS\ACLResource();
    $table = $resource->table(new Filament\Tables\Table());

    $actions = $table->getRecordActions();
    expect($actions)->toHaveKey('edit');
    expect($actions)->toHaveKey('delete');
});
