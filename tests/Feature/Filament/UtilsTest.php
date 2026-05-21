<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable as HasTableContract;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\Filament\HasFormHarness;
use Modules\Core\Tests\Stubs\Filament\HasRecordsHarness;

beforeEach(function (): void {
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $this->admin_role = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($this->admin_role);
    $this->actingAs($this->admin);
});

it('executes HasForm configureForm workflow', function (): void {
    $schema = $this->createMock(Schema::class);
    $schema->method('getModel')->willReturn(User::class);

    HasFormHarness::run($schema);

    expect(HasFormHarness::$loaded_permissions)->toBeTrue();
});

it('returns create action when user can create records', function (): void {
    $setting = new Setting;
    $permission_name = sprintf(
        '%s.%s.create',
        $setting->getConnectionName() ?? 'default',
        $setting->getTable(),
    );

    $permission = Permission::factory()->create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);
    $this->admin_role->givePermissionTo($permission);

    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    $actions = $harness->callHeaderActions();

    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toBeInstanceOf(CreateAction::class);
});

it('returns no header actions when user cannot create records', function (): void {
    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    expect($harness->callHeaderActions())->toBe([]);
});

it('shares table fetch duration and returns parent records', function (): void {
    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    $records = $harness->getTableRecords();

    expect($records)->toBeInstanceOf(Collection::class)
        ->and($records->count())->toBe(3)
        ->and(view()->shared('tableFetchDurationSeconds'))->not->toBeNull();
});

it('applies configured groups while building the table', function (): void {
    $livewire = $this->createStub(HasTableContract::class);
    $table = Table::make($livewire);
    $harness = new HasRecordsHarness($table);

    $groups_property = new ReflectionProperty(HasRecordsHarness::class, 'groups');
    $groups_property->setAccessible(true);
    $groups_property->setValue($harness, [Group::make('group_name')]);

    $result_table = $harness->callMakeTable();

    expect($result_table->getGroups())->toHaveCount(1);
});
