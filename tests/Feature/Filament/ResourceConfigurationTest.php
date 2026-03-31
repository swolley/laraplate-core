<?php

declare(strict_types=1);

use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Filament\Resources\Permissions\PermissionResource;
use Modules\Core\Filament\Resources\Roles\RoleResource;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    /** @var App\Models\User $admin */
    $admin = App\Models\User::query()->create(User::factory()->raw([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]));
    $this->admin = $admin;

    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($adminRole);
});

it('exposes expected slugs and empty relation groups for core resources', function (): void {
    expect(UserResource::getSlug())->toBe('core/users')
        ->and(UserResource::getRelations())->toBe([]);

    expect(RoleResource::getSlug())->toBe('core/roles')
        ->and(RoleResource::getRelations())->toBe([]);

    expect(PermissionResource::getSlug())->toBe('core/permissions')
        ->and(PermissionResource::getRelations())->toBe([]);

    expect(SettingResource::getSlug())->toBe('core/settings')
        ->and(SettingResource::getRelations())->toBe([]);

    expect(LicenseResource::getSlug())->toBe('core/licenses')
        ->and(LicenseResource::getRelations())->toBe([]);

    expect(CronJobResource::getSlug())->toBe('core/cron-jobs')
        ->and(CronJobResource::getRelations())->toBe([]);

    expect(ACLResource::getSlug())->toBe('core/acls')
        ->and(ACLResource::getRelations())->toBe([]);
});

it('configures user resource table and applies eager loading scope', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => UserResource::getModel()::query());

    UserResource::table($table);

    $query = $table->getQuery();
    expect($query)->not->toBeNull();
    expect($query->getEagerLoads())->toHaveKey('roles');
});

it('configures role resource table and applies eager loading scope', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => RoleResource::getModel()::query());

    RoleResource::table($table);

    $query = $table->getQuery();
    expect($query)->not->toBeNull();
    expect($query->getEagerLoads())->toHaveKey('permissions');
});

it('configures acl resource table and applies eager loading scope', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => ACLResource::getModel()::query());

    ACLResource::table($table);

    $query = $table->getQuery();
    expect($query)->not->toBeNull();
    expect($query->getEagerLoads())->toHaveKey('permission');
});

it('configures permission resource table without throwing', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => PermissionResource::getModel()::query());

    PermissionResource::table($table);

    expect($table->getQuery())->not->toBeNull();
});

it('configures setting resource table without throwing', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => SettingResource::getModel()::query());

    SettingResource::table($table);

    expect($table->getQuery())->not->toBeNull();
});

it('configures license resource table without throwing', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => LicenseResource::getModel()::query());

    LicenseResource::table($table);

    expect($table->getQuery())->not->toBeNull();
});

it('configures cron job resource table without throwing', function (): void {
    $this->actingAs($this->admin);

    $livewire = $this->createStub(HasTable::class);
    $table = Table::make($livewire);
    $table->query(fn () => CronJobResource::getModel()::query());

    CronJobResource::table($table);

    expect($table->getQuery())->not->toBeNull();
});
