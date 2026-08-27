<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\HasOnceHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;

it('memoizes permission lookups within one request and forgets them afterwards', function (): void {
    $permission_name = 'default.memoized_' . uniqid() . '.select';

    // Seeding style copied from Modules/Core/tests/Integration/Services/AclRoleScopedTest.php:35
    Permission::create(['name' => $permission_name, 'guard_name' => 'web']);

    // Resolve the service once, outside the query log: the memo must be cleared by the
    // request boundary, not merely by getting a different service instance.
    $service = app(AuthorizationService::class);
    $method = new ReflectionMethod($service, 'resolvePermission');

    $resolve = function () use ($service, $method, $permission_name): void {
        $method->invoke($service, $permission_name);
        $method->invoke($service, $permission_name);
    };

    DB::enableQueryLog();
    DB::flushQueryLog();

    $resolve();

    // Two calls, two queries: the permission lookup itself, plus the permission
    // existence check that HasValidations fires on the retrieved Permission model.
    // Both are memoized, so the second call adds nothing — unmemoized it would be four.
    expect(DB::getQueryLog())->toHaveCount(2);

    // Octane's FlushOnce listener does exactly this between operations.
    Once::flush();
    DB::flushQueryLog();

    $resolve();

    // After the boundary the lookups hit the database again: no stale worker state.
    expect(DB::getQueryLog())->toHaveCount(2);
});

it('keys memoized permission checks on the user identity instead of the object handle', function (): void {
    $user_a = User::factory()->create();
    $user_b = User::factory()->create();

    expect($user_a)->toBeInstanceOf(HasOnceHash::class)
        ->and($user_a->onceHash())->not->toBe($user_b->onceHash());
});
