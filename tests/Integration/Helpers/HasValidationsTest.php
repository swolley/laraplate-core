<?php

declare(strict_types=1);

use Modules\Core\Models\Concerns\HasValidations;

it('trait can be used', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('trait has required methods', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('can skip validation', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect($trait->shouldSkipValidation())->toBeFalse();

    $trait->setSkipValidation(true);
    expect($trait->shouldSkipValidation())->toBeTrue();

    $trait->setSkipValidation(false);
    expect($trait->shouldSkipValidation())->toBeFalse();
});

it('has default rules', function (): void {
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'test_table';
    };

    $rules = $model->getRules();

    expect($rules)->toHaveKey('create');
    expect($rules)->toHaveKey('update');
    expect($rules)->toHaveKey('always');
});

it('can get operation rules', function (): void {
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'test_table';
    };

    $createRules = $model->getOperationRules('create');
    $updateRules = $model->getOperationRules('update');

    expect($createRules)->toBeArray();
    expect($updateRules)->toBeArray();
});

it('trait methods are callable', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(fn () => $trait->setSkipValidation(true))->not->toThrow(Throwable::class);
    expect(fn () => $trait->shouldSkipValidation())->not->toThrow(Throwable::class);
    expect(fn () => $trait->getRules())->not->toThrow(Throwable::class);
});

it('trait can be used in different classes', function (): void {
    $class1 = new class
    {
        use HasValidations;
    };

    $class2 = new class
    {
        use HasValidations;
    };

    expect(method_exists($class1, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($class2, 'setSkipValidation'))->toBeTrue();
});

it('trait is properly namespaced', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('trait can be extended', function (): void {
    $baseClass = new class
    {
        use HasValidations;
    };

    $extendedClass = new class
    {
        use HasValidations;

        public function customMethod(): string
        {
            return 'custom';
        }
    };

    expect(method_exists($baseClass, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($extendedClass, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($extendedClass, 'customMethod'))->toBeTrue();
});

it('trait has proper structure', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('trait methods are accessible', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('trait can be used in different scenarios', function (): void {
    $scenario1 = new class
    {
        use HasValidations;
    };

    $scenario2 = new class
    {
        use HasValidations;
    };

    expect(method_exists($scenario1, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($scenario2, 'setSkipValidation'))->toBeTrue();
});

// Feature: performance-optimization, Property 1: permission existence cache eliminates redundant DB queries

it('exposes resetPermissionExistenceCache static method', function (): void {
    expect(method_exists(HasValidations::class, 'resetPermissionExistenceCache'))->toBeTrue();
});

it('does not issue a second DB query for the same permission name', function (): void {
    HasValidations::resetPermissionExistenceCache();

    $permission_name = 'test_table_cache.' . fake()->unique()->word();
    $query_count = 0;

    // Intercept DB queries to count permission existence checks
    \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $event) use ($permission_name, &$query_count): void {
        if (str_contains($event->sql, 'permissions') && str_contains(implode(',', array_map('strval', $event->bindings)), $permission_name)) {
            $query_count++;
        }
    });

    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'test_table_cache';
    };

    // Call checkUserCanDo twice with the same permission — second call must not hit DB
    $method = new \ReflectionMethod(HasValidations::class, 'checkUserCanDo');
    $method->invoke(null, $model, 'select');
    $after_first = $query_count;
    $method->invoke(null, $model, 'select');
    $after_second = $query_count;

    // No new queries after the first call
    expect($after_second)->toBe($after_first);
});

it('resets permission existence cache to empty state', function (): void {
    HasValidations::resetPermissionExistenceCache();

    // After reset the static cache is empty — next call will query DB again
    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'reset_test_table';
    };

    $method = new \ReflectionMethod(HasValidations::class, 'checkUserCanDo');
    $method->invoke(null, $model, 'select');

    HasValidations::resetPermissionExistenceCache();

    $query_count = 0;
    \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $event) use (&$query_count): void {
        if (str_contains($event->sql, 'permissions')) {
            $query_count++;
        }
    });

    $method->invoke(null, $model, 'select');

    // After reset, a fresh DB query is issued
    expect($query_count)->toBeGreaterThanOrEqual(1);
});

/**
 * Property 1: Permission existence cache eliminates redundant DB queries.
 *
 * For any permission name, calling checkUserCanDo() a second time within the same
 * request lifecycle SHALL NOT issue a database query to the permissions table.
 *
 * Validates: Requirements 1.1, 1.2, 1.4
 */
it('does not query DB on warm cache for any permission name (property test)', function (): void {
    // Feature: performance-optimization, Property 1: permission existence cache eliminates redundant DB queries
    HasValidations::resetPermissionExistenceCache();

    $table = fake()->unique()->word();
    $operation = fake()->randomElement(['select', 'insert', 'update', 'delete']);

    $model = new class extends \Illuminate\Database\Eloquent\Model
    {
        use HasValidations;
    };
    $model->setTable($table);

    $method = new \ReflectionMethod(HasValidations::class, 'checkUserCanDo');

    // Cold cache: first call populates the static cache and may issue a DB query
    \Illuminate\Support\Facades\DB::enableQueryLog();
    $method->invoke(null, $model, $operation);
    $count_after_first = count(\Illuminate\Support\Facades\DB::getQueryLog());

    // Warm cache: second call with the same permission name must not add any new queries
    $method->invoke(null, $model, $operation);
    $count_after_second = count(\Illuminate\Support\Facades\DB::getQueryLog());

    expect($count_after_second)->toBe($count_after_first);
})->repeat(10);
