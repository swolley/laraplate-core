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

