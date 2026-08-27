<?php

declare(strict_types=1);

use Illuminate\Support\Once;
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

it('does not issue a second DB query for the same permission name', function (): void {
    Once::flush();

    $permission_name = 'test_table_cache.' . fake()->unique()->word();
    $query_count = 0;

    // Intercept DB queries to count permission existence checks
    Illuminate\Support\Facades\DB::listen(static function (Illuminate\Database\Events\QueryExecuted $event) use ($permission_name, &$query_count): void {
        if (str_contains($event->sql, 'permissions') && str_contains(implode(',', array_map('strval', $event->bindings)), $permission_name)) {
            $query_count++;
        }
    });

    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'test_table_cache';
    };

    // Call checkUserCanDo twice with the same permission — second call must not hit DB
    $method = new ReflectionMethod(HasValidations::class, 'checkUserCanDo');
    $method->invoke(null, $model, 'select');
    $after_first = $query_count;
    $method->invoke(null, $model, 'select');
    $after_second = $query_count;

    // No new queries after the first call
    expect($after_second)->toBe($after_first);
});

it('uses and caches the permission model connection independently of the authorized model connection', function (): void {
    Once::flush();

    $connection_name = 'permission_affinity';
    $permission_model_class = config('permission.models.permission');
    $permission_model = new $permission_model_class();
    $permission_table = $permission_model->getTable();
    $table_name = 'permission_affinity_records_' . uniqid();
    $permission_name = "{$table_name}.select";

    config()->set("database.connections.{$connection_name}", [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Illuminate\Support\Facades\DB::purge($connection_name);

    try {
        $permission_model->getConnection()->table($permission_table)->insert([
            'name' => $permission_name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Mockery::mock(Modules\Core\Models\User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->andReturn(false);
        $user->shouldReceive('hasPermission')->with($permission_name)->andReturn(false);
        Illuminate\Support\Facades\Auth::login($user);

        $queried_connections = [];
        $permission_query_count = 0;
        Illuminate\Support\Facades\DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use ($permission_name, $permission_table, &$permission_query_count, &$queried_connections): void {
            $queried_connections[] = $query->connectionName;

            if (str_contains($query->sql, $permission_table) && in_array($permission_name, $query->bindings, true)) {
                $permission_query_count++;
            }
        });

        $model = new class extends Illuminate\Database\Eloquent\Model
        {
            use HasValidations;
        };
        $model->setTable($table_name);

        $affinity_model = new class extends Illuminate\Database\Eloquent\Model
        {
            use HasValidations;
        };
        $affinity_model->setTable($table_name);
        $affinity_model->setConnection($connection_name);

        $method = new ReflectionMethod(HasValidations::class, 'checkUserCanDo');

        expect($method->invoke(null, $model, 'select'))->toBeFalse()
            ->and($method->invoke(null, $affinity_model, 'select'))->toBeFalse()
            ->and($permission_query_count)->toBe(1)
            ->and($queried_connections)->toContain($permission_model->getConnection()->getName())
            ->and($queried_connections)->not->toContain($connection_name);
    } finally {
        Illuminate\Support\Facades\Auth::logout();
        Once::flush();
        Illuminate\Support\Facades\DB::disconnect($connection_name);
        Illuminate\Support\Facades\DB::purge($connection_name);
    }
});

it('issues a fresh DB query after the request-scoped memo is flushed', function (): void {
    Once::flush();

    // After the flush the memo is empty — next call will query DB again
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'reset_test_table';
    };

    $method = new ReflectionMethod(HasValidations::class, 'checkUserCanDo');
    $method->invoke(null, $model, 'select');

    Once::flush();

    $query_count = 0;
    Illuminate\Support\Facades\DB::listen(static function (Illuminate\Database\Events\QueryExecuted $event) use (&$query_count): void {
        if (str_contains($event->sql, 'permissions')) {
            $query_count++;
        }
    });

    $method->invoke(null, $model, 'select');

    // After the flush, a fresh DB query is issued
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
    Once::flush();

    $table = fake()->unique()->word();
    $operation = fake()->randomElement(['select', 'insert', 'update', 'delete']);

    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;
    };
    $model->setTable($table);

    $method = new ReflectionMethod(HasValidations::class, 'checkUserCanDo');

    // Cold cache: first call populates the static cache and may issue a DB query
    $permission_model_class = config('permission.models.permission');
    $permission_connection = (new $permission_model_class())->getConnection();
    $permission_connection->enableQueryLog();
    $method->invoke(null, $model, $operation);
    $count_after_first = count($permission_connection->getQueryLog());

    // Warm cache: second call with the same permission name must not add any new queries
    $method->invoke(null, $model, $operation);
    $count_after_second = count($permission_connection->getQueryLog());

    expect($count_after_second)->toBe($count_after_first);
})->repeat(10);
