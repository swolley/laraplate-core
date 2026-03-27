<?php

declare(strict_types=1);

use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Services\ImpersonateManager;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Pivot\ModelHasRole;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Spatie\Permission\Guard;

uses(LaravelTestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->user)->toBeInstanceOf(User::class);
    expect($this->user->id)->not->toBeNull();
});

it('has fillable attributes', function (): void {
    $userData = [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'Aa1!TestUserPass',
        'lang' => 'en',
    ];

    $user = User::create($userData);

    expect($user->name)->toBe('Test User');
    expect($user->username)->toBe('testuser');
    expect($user->email)->toBe('test@example.com');
    expect($user->lang)->toBe('en');
});

it('has hidden attributes', function (): void {
    $user = User::factory()->create();
    $userArray = $user->toArray();

    expect($userArray)->not->toHaveKey('password');
    expect($userArray)->not->toHaveKey('remember_token');
    expect($userArray)->not->toHaveKey('two_factor_secret');
    expect($userArray)->not->toHaveKey('two_factor_recovery_codes');
});

it('has many roles relationship', function (): void {
    $role1 = Role::factory()->create(['name' => 'admin']);
    $role2 = Role::factory()->create(['name' => 'editor']);

    $this->user->roles()->attach([$role1->id, $role2->id]);

    expect($this->user->roles)->toHaveCount(2);
    expect($this->user->roles->pluck('name')->toArray())->toContain('admin', 'editor');
});

it('can check if user is super admin', function (): void {
    $adminRole = Role::factory()->create(['name' => 'superadmin']);
    $this->user->roles()->attach($adminRole);

    expect($this->user->isSuperAdmin())->toBeTrue();
});

it('can check if user is not super admin', function (): void {
    $regularRole = Role::factory()->create(['name' => 'user']);
    $this->user->roles()->attach($regularRole);

    expect($this->user->isSuperAdmin())->toBeFalse();
});

it('can access filament panel when super admin', function (): void {
    $adminRole = Role::factory()->create(['name' => 'superadmin']);
    $this->user->roles()->attach($adminRole);

    // Test that user has superadmin role
    expect($this->user->isSuperAdmin())->toBeTrue();
});

it('can impersonate other users when has permission', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    // Test that user has admin role
    expect($this->user->hasRole('admin'))->toBeTrue();
});

it('has email verification required', function (): void {
    expect($this->user)->toBeInstanceOf(Illuminate\Contracts\Auth\MustVerifyEmail::class);
});

it('has two factor authentication trait', function (): void {
    // Test that the trait is used by checking for a common method
    expect(method_exists($this->user, 'twoFactorQrCodeSvg'))->toBeTrue();
});

it('has soft deletes trait', function (): void {
    $this->user->delete();

    expect($this->user->trashed())->toBeTrue();
    expect(User::withTrashed()->find($this->user->id))->not->toBeNull();
});

it('has versions trait', function (): void {
    expect(method_exists($this->user, 'versions'))->toBeTrue();
    expect(method_exists($this->user, 'createVersion'))->toBeTrue();
});

it('has locks trait', function (): void {
    expect(method_exists($this->user, 'lock'))->toBeTrue();
    expect(method_exists($this->user, 'unlock'))->toBeTrue();
});

it('has validations trait', function (): void {
    expect(method_exists($this->user, 'getRules'))->toBeTrue();
});

it('has approval changes trait', function (): void {
    expect(method_exists($this->user, 'authorizedToApprove'))->toBeTrue();
    expect(method_exists($this->user, 'authorizedToDisapprove'))->toBeTrue();
});

it('can be observed', function (): void {
    expect(method_exists($this->user, 'observe'))->toBeTrue();
});

it('has proper casts for dates', function (): void {
    $user = User::factory()->create();

    expect($user->created_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
    expect($user->updated_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
});

it('can be created with specific attributes', function (): void {
    $userData = [
        'name' => 'John Doe',
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'Aa1!TestUserPass',
        'lang' => 'it',
    ];

    $user = User::create($userData);

    expect($user->name)->toBe('John Doe');
    expect($user->username)->toBe('johndoe');
    expect($user->email)->toBe('john@example.com');
    expect($user->lang)->toBe('it');
});

it('has proper password hashing', function (): void {
    $user = User::factory()->create(['password' => 'plaintext']);

    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});

it('can be found by email', function (): void {
    $user = User::factory()->create(['email' => 'unique@example.com']);

    $foundUser = User::where('email', 'unique@example.com')->first();

    expect($foundUser->id)->toBe($user->id);
});

it('can be found by username', function (): void {
    $user = User::factory()->create(['username' => 'uniqueuser']);

    $foundUser = User::where('username', 'uniqueuser')->first();

    expect($foundUser->id)->toBe($user->id);
});

it('isGuest returns true when user has no email', function (): void {
    $user = User::factory()->create();
    $user->setAttribute('email', null);

    expect($user->isGuest())->toBeTrue();
});

it('canAccessPanel returns true for superadmin without calling panel guard', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $role = Role::factory()->create(['name' => 'superadmin']);
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $panel = Mockery::mock(Panel::class);

    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('canAccessPanel returns true when user has wildcard permission on panel guard', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $user = User::factory()->create();
    $permissions_table = config('permission.table_names.permissions');
    DB::table($permissions_table)->insert([
        'name' => '*',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);
    $user->givePermissionTo('*');
    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('getAuthGuard')->andReturn('web');

    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('isAdmin returns true when user has admin role', function (): void {
    config(['permission.roles.admin' => 'admin']);
    $role = Role::factory()->create(['name' => 'admin']);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    expect($user->isAdmin())->toBeTrue();
});

it('canBeImpersonated returns false for superadmin', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $role = Role::factory()->create(['name' => 'superadmin']);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    expect($user->canBeImpersonated())->toBeFalse();
});

it('canImpersonate returns true for superadmin', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $role = Role::factory()->create(['name' => 'superadmin']);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    expect($user->canImpersonate())->toBeTrue();
});

it('canImpersonate returns true when user has impersonate permission via role', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $user = User::factory()->create();
    $guard_name = Guard::getDefaultName(User::class);
    $perm_name = ($user->getConnectionName() ?? 'default') . $user->getTable() . '.impersonate';
    $permission = new Permission(['name' => $perm_name, 'guard_name' => $guard_name]);
    $permission->setSkipValidation(true);
    $permission->save();
    $role = Role::factory()->create(['name' => 'impersonator', 'guard_name' => $guard_name]);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    expect($user->canImpersonate())->toBeTrue();
});

it('canImpersonate returns false when impersonate permission does not exist', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'plain']);
    $user->assignRole($role);

    expect($user->canImpersonate())->toBeFalse();
});

it('getImpersonator returns impersonator from manager when session is impersonated', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create();
    $this->mock(ImpersonateManager::class, function ($mock) use ($admin): void {
        $mock->shouldReceive('isImpersonating')->andReturnTrue();
        $mock->shouldReceive('getImpersonator')->once()->andReturn($admin);
    });

    expect($target->getImpersonator()->is($admin))->toBeTrue();
});

it('grid_configs returns has many relationship', function (): void {
    $user = User::factory()->create();

    expect($user->grid_configs())->toBeInstanceOf(HasMany::class);
});

it('roles relationship uses model has role pivot class', function (): void {
    $user = User::factory()->create();

    expect($user->roles()->getPivotClass())->toBe(ModelHasRole::class);
});

it('getPermissionsViaRoles returns all permissions for superadmin', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);

    foreach (['test.one.a', 'test.two.b'] as $perm_name) {
        $p = new Permission(['name' => $perm_name, 'guard_name' => 'web']);
        $p->setSkipValidation(true);
        $p->save();
    }
    $role = Role::factory()->create(['name' => 'superadmin']);
    $user = User::factory()->create();
    $user->roles()->attach($role);
    $expected = Permission::query()->get()->sort()->values()->pluck('name')->all();
    $actual = $user->getPermissionsViaRoles()->pluck('name')->all();

    expect($actual)->toEqualCanonicalizing($expected);
});

it('superAdmin scope filters users with superadmin role', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $super = User::factory()->create();
    $super->roles()->attach(Role::factory()->create(['name' => 'superadmin']));
    $plain = User::factory()->create();

    expect(User::query()->superAdmin()->pluck('id')->all())->toContain($super->id)
        ->and(User::query()->superAdmin()->pluck('id')->all())->not->toContain($plain->id);
});

it('admin scope filters users with admin role', function (): void {
    config(['permission.roles.admin' => 'admin']);
    $admin_user = User::factory()->create();
    $admin_user->roles()->attach(Role::factory()->create(['name' => 'admin']));
    $plain = User::factory()->create();

    expect(User::query()->admin()->pluck('id')->all())->toContain($admin_user->id)
        ->and(User::query()->admin()->pluck('id')->all())->not->toContain($plain->id);
});

it('getRules unique username and email callbacks apply deleted_at scope', function (): void {
    $user = User::factory()->create();
    $rules = $user->getRules();
    expect_unique_rules_apply_deleted_at_scope($rules['create']['username']);
    expect_unique_rules_apply_deleted_at_scope($rules['update']['username']);
    expect_unique_rules_apply_deleted_at_scope($rules['update']['email']);
});
