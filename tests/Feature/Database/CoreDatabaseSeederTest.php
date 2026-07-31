<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Spatie\Permission\Models\Role as BaseRole;

/**
 * @var Closure(): array<string,string> $default_user_roles username => expected role name
 */
$default_user_roles = function (): array {
    $roles = CoreDatabaseSeeder::getDefaultUserRoles();

    return [
        (string) config('permission.users.superadmin') => $roles['superadmin'],
        (string) config('permission.users.admin') => $roles['admin'],
        (string) config('permission.users.guest') => $roles['guest'],
        (string) config('permission.users.system') => $roles['system'],
    ];
};

/**
 * @var Closure(): void $seed
 */
$seed = function (): void {
    Artisan::call('db:seed', ['--class' => CoreDatabaseSeeder::class, '--no-interaction' => true]);
};

beforeEach($seed);

it('creates every default user with its role', function () use ($default_user_roles): void {
    $user_class = user_class();

    foreach ($default_user_roles() as $username => $role_name) {
        $user = $user_class::query()->withoutGlobalScopes()->where('username', $username)->first();

        expect($user)->not->toBeNull("Default user [{$username}] must be seeded.");
        expect($user->roles()->pluck('name')->all())->toContain($role_name);
    }
});

it('does not duplicate default users when seeded twice', function () use ($default_user_roles, $seed): void {
    $user_class = user_class();

    $seed();

    foreach (array_keys($default_user_roles()) as $username) {
        expect($user_class::query()->withoutGlobalScopes()->where('username', $username)->count())
            ->toBe(1, "Default user [{$username}] must be seeded exactly once.");
    }
});

it('seeds clearUserAssignedLicenses is_active from the runtime license setting', function (): void {
    $cron = CronJob::query()->withoutGlobalScopes()->where('name', 'clearUserAssignedLicenses')->first();

    expect($cron)->not->toBeNull();
    expect((bool) $cron->is_active)->toBe((bool) config('core.enable_user_licenses', false));
});

it('writes model-owned seeder records on the owning model connection', function (): void {
    /** @var class-string<BaseRole> $role_class */
    $role_class = config('permission.models.role');

    $models = [
        new ReflectionClass(user_class())->newInstanceWithoutConstructor(),
        new ReflectionClass($role_class)->newInstanceWithoutConstructor(),
        new Setting,
        new CronJob,
    ];

    foreach ($models as $model) {
        $rows = $model->getConnection()->table($model->getTable())->count();

        expect($rows)->toBeGreaterThan(0, $model::class . ' rows must exist on its own connection.');
    }
});
