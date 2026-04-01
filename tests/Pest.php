<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Default: lightweight PHPUnit TestCase for Unit tests (no app/DB bootstrap).
| Feature tests use LaravelTestCase via ->in(__DIR__.'/Feature'). Unit files that
| need the app declare uses(Modules\Core\Tests\LaravelTestCase::class).
|
| Note: A global uses(LaravelTestCase::class) alone does not attach the case to each
| test file in this setup; Feature must use ->in(...) or per-file uses().
|
| Speed: prefer `composer run test:pest` or `vendor/bin/pest --parallel` during development.
| `composer test` runs type coverage, coverage, PHPStan, Pint, etc., and is slower by design.
|--------------------------------------------------------------------------
*/
uses(Modules\Core\Tests\TestCase::class);

uses(Modules\Core\Tests\LaravelTestCase::class)->in(__DIR__ . '/Feature');

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

/**
 * Invoke Unique rule query callbacks that scope non-trashed rows (deleted_at null).
 */
function expect_unique_rules_apply_deleted_at_scope(iterable $rules): void
{
    foreach ($rules as $rule) {
        if (! $rule instanceof Illuminate\Validation\Rules\Unique) {
            continue;
        }

        foreach ($rule->queryCallbacks() as $callback) {
            $query = Mockery::mock(Illuminate\Database\Query\Builder::class);
            $query->shouldReceive('where')->once()->with('deleted_at', null);
            $callback($query);
        }
    }
}
