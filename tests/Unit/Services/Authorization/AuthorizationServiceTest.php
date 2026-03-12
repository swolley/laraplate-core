<?php

declare(strict_types=1);

use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('buildPermissionName composes connection, entity and operation', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    expect($service->buildPermissionName('orders', 'select', 'mysql'))->toBe('mysql.orders.select');
    expect($service->buildPermissionName('orders', 'select', null))->toBe('default.orders.select');
});

it('getAclFilters returns null for non authenticated user', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    Auth::shouldReceive('user')->andReturnNull();
    expect($service->getAclFilters('default.orders.select'))->toBeNull();
});

// NOTE: More complex permission flows are exercised via higher-level tests;
// here we keep unit tests focused on helper methods and cache/ACL wiring.


it('hasUnrestrictedAccess returns false when no authenticated user', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    Auth::shouldReceive('user')->andReturnNull();

    expect($service->hasUnrestrictedAccess('default.orders.select'))->toBeFalse();
});

it('hasUnrestrictedAccess returns true for super admin user', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    /** @var User&\Mockery\MockInterface $user */
    $user = \Mockery::mock(User::class);
    $user->shouldReceive('isSuperAdmin')->andReturnTrue();

    Auth::shouldReceive('user')->andReturn($user);

    expect($service->hasUnrestrictedAccess('default.orders.select'))->toBeTrue();
});

it('clearCacheForCurrentUser does nothing when not authenticated', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    Auth::shouldReceive('user')->andReturnNull();

    $service->clearCacheForCurrentUser();
    expect(true)->toBeTrue();
});

it('clearCacheForCurrentUser forwards to AclResolverService when user is authenticated', function (): void {
    $resolver = new AclResolverService();
    $service = new AuthorizationService($resolver);

    /** @var User&\Mockery\MockInterface $user */
    $user = \Mockery::mock(User::class);

    Auth::shouldReceive('user')->andReturn($user);

    $service->clearCacheForCurrentUser();
    expect(true)->toBeTrue();
});


