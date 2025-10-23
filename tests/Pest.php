<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
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
