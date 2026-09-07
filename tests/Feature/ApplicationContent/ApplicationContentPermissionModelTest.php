<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Once;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalProviderRegistry;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalService;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\ApplicationContent\Exceptions\ApplicationContentUnavailableException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Tests\Stubs\ApplicationContent\PermissionModelFakeProvider;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * The descriptor entity is deliberately NOT the model's table name: `User`
 * lives in `users`, but the source's logical entity is `notusers`. The seeded
 * permission is keyed on the table (`default.users.select`), exactly as
 * PermissionsRefreshCommand names it. So a provider that authorizes off the
 * short entity would check the non-existent `default.notusers.select` and deny
 * every non-superadmin; authorizing off the model table is the fix.
 */
beforeEach(function (): void {
    Cache::flush();
    Once::flush();

    $this->descriptor = new ApplicationContentSourceDescriptor(
        'core.notusers',
        'core',
        'notusers',
        ['en'],
        ['lexical'],
        ['user_help'],
    );
    $this->provider = new PermissionModelFakeProvider($this->descriptor, User::class);
    $this->registry = new ApplicationContentRetrievalProviderRegistry;
    $this->registry->register($this->provider);
    $this->service = new ApplicationContentRetrievalService(
        $this->registry,
        new AuthorizationService(new AclResolverService),
    );

    $this->tablePermission = Permission::query()->firstOrCreate([
        'name' => 'default.users.select',
        'guard_name' => 'web',
    ]);
});

function permissionModelRequest(User $user): Request
{
    $request = Request::create('/app/ai/messages', 'POST');
    $request->setUserResolver(static fn (): User => $user);

    return $request;
}

it('gates a non-superadmin on the model table permission, not the short entity', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo($this->tablePermission);
    Auth::login($user);

    $result = $this->service->retrieve(
        permissionModelRequest($user),
        new ApplicationContentQuery('core.notusers', 'visible record', 'en', 5),
    );

    expect($this->provider->calls)->toBe(1)
        ->and($this->provider->capturedAuthorization?->permissionName)->toBe('default.users.select')
        ->and($result->source)->toBe('core.notusers');
});

it('fails closed when the non-superadmin lacks the model table permission', function (): void {
    $user = User::factory()->create();
    Auth::login($user);

    expect(fn () => $this->service->retrieve(
        permissionModelRequest($user),
        new ApplicationContentQuery('core.notusers', 'visible record', 'en', 5),
    ))->toThrow(ApplicationContentUnavailableException::class)
        ->and($this->provider->calls)->toBe(0);
});
