<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Concerns\HasValidations;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;

uses(RefreshDatabase::class);

/**
 * Registers the table-level select permission so the guard actually runs. Without a registered
 * permission the check reads "not registered" and allows the operation, which would prove nothing.
 */
function registerSelectPermission(Model $model): string
{
    $name = PermissionName::forModel($model, 'select');

    /** @var class-string<Model> $permission_class */
    $permission_class = config('permission.models.permission');

    $permission_class::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);

    return $name;
}

it('lets a superadmin read a role when their own roles have not been loaded yet', function (): void {
    // The reported symptom: a 403 on the first request after signing in, when nothing has warmed
    // the acting user's roles. Deciding whether they may read a role meant reading their roles.
    registerSelectPermission(new Role());

    $role = Role::query()->firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    Auth::login(User::query()->findOrFail($user->getKey()));

    expect(Auth::user()->relationLoaded('roles'))->toBeFalse()
        ->and(Role::query()->first()?->name)->toBe('superadmin');
});

it('resolves a plain user own roles instead of handing back a null relation', function (): void {
    // Eloquent does not reload a relation that is already loading, so the circle did not merely
    // recurse: `$user->roles` came back null and Spatie fell over inside `hasRole()`.
    registerSelectPermission(new Role());

    $role = Role::query()->firstOrCreate(['name' => 'publisher', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    Auth::login(User::query()->findOrFail($user->getKey()));

    expect(Auth::user()->roles->pluck('name')->all())->toBe(['publisher'])
        ->and(Auth::user()->isSuperAdmin())->toBeFalse();
});

it('still refuses an ordinary model to a user without the permission', function (): void {
    // The exemption is meant to be narrow. Anything that is not a role or a permission keeps the
    // per-row check, so this must stay red for a user who was granted nothing.
    $model = new class extends Model
    {
        use HasValidations;

        protected $table = 'core_taxonomies';
    };

    registerSelectPermission($model);

    Auth::login(User::factory()->create());

    $check = (new ReflectionMethod($model::class, 'checkUserCanDo'))->invoke(null, $model, 'select');

    expect($check)->toBeFalse();
});
