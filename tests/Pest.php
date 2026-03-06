<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Single test case (LaravelTestCase) so --parallel can run: each worker
| resolves the app via Tests\CreatesApplication. Unit tests run with the
| same bootstrap (slightly slower but parallel-safe).
|--------------------------------------------------------------------------
*/
uses(Tests\LaravelTestCase::class);

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Create a test user with admin role.
 */
function createAdminUser(): Modules\Core\Models\User
{
    $user = Modules\Core\Models\User::factory()->create();
    $adminRole = Modules\Core\Models\Role::factory()->create(['name' => 'admin']);
    $user->roles()->attach($adminRole);

    return $user;
}

/**
 * Create a test user with specific role.
 */
function createUserWithRole(string $roleName): Modules\Core\Models\User
{
    $user = Modules\Core\Models\User::factory()->create();
    $role = Modules\Core\Models\Role::factory()->create(['name' => $roleName]);
    $user->roles()->attach($role);

    return $user;
}

/**
 * Assert that a model has the expected attributes.
 */
function expectModelAttributes($model, array $attributes): void
{
    foreach ($attributes as $key => $value) {
        expect($model->{$key})->toBe($value);
    }
}

/**
 * Assert that a model exists in database with given attributes.
 */
function assertModelExists(string $modelClass, array $attributes): void
{
    expect($modelClass::where($attributes)->exists())->toBeTrue();
}

/**
 * Assert that a model does not exist in database with given attributes.
 */
function assertModelNotExists(string $modelClass, array $attributes): void
{
    expect($modelClass::where($attributes)->exists())->toBeFalse();
}
