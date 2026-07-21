<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalProviderRegistry;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalService;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderInterface;
use Modules\Core\ApplicationContent\Data\ApplicationContentAuthorization;
use Modules\Core\ApplicationContent\Data\ApplicationContentHit;
use Modules\Core\ApplicationContent\Data\ApplicationContentQuery;
use Modules\Core\ApplicationContent\Data\ApplicationContentResult;
use Modules\Core\ApplicationContent\Data\ApplicationContentSourceDescriptor;
use Modules\Core\ApplicationContent\Exceptions\ApplicationContentUnavailableException;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

final class CapturingApplicationContentProvider implements ApplicationContentRetrievalProviderInterface
{
    public int $calls = 0;

    public ?ApplicationContentAuthorization $capturedAuthorization = null;

    public ?Throwable $failure = null;

    public ?ApplicationContentResult $result = null;

    public function __construct(public ApplicationContentSourceDescriptor $source) {}

    public function descriptor(): ApplicationContentSourceDescriptor
    {
        return $this->source;
    }

    public function retrieve(
        ApplicationContentQuery $query,
        ApplicationContentAuthorization $authorization,
    ): ApplicationContentResult {
        $this->calls++;
        $this->capturedAuthorization = $authorization;

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->result ?? new ApplicationContentResult(
            $query->source,
            [applicationContentServiceHit()],
            'lexical',
            false,
        );
    }
}

function applicationContentServiceHit(int $key = 1): ApplicationContentHit
{
    return new ApplicationContentHit(
        'core-user-' . $key,
        'core.users',
        'core',
        'users',
        $key,
        'Visible application information.',
        'Visible record',
        '/app/core/users/' . $key,
        'en',
        'lexical',
        0.8,
        null,
        false,
    );
}

beforeEach(function (): void {
    Cache::flush();
    AuthorizationService::resetPermissionCache();
    $this->permission = Permission::query()->firstOrCreate([
        'name' => 'default.users.select',
        'guard_name' => 'web',
    ]);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo($this->permission);
    Auth::login($this->user);
    $this->request = Request::create('/app/ai/messages', 'POST');
    $this->request->setUserResolver(fn (): User => $this->user);
    $this->provider = new CapturingApplicationContentProvider(
        new ApplicationContentSourceDescriptor(
            'core.users',
            'core',
            'users',
            ['en'],
            ['lexical'],
            ['user_help'],
        ),
    );
    $this->registry = new ApplicationContentRetrievalProviderRegistry;
    $this->registry->register($this->provider);
    $this->service = new ApplicationContentRetrievalService(
        $this->registry,
        new AuthorizationService(new AclResolverService),
    );
});

it('authorizes and returns bounded provider evidence', function (): void {
    $result = $this->service->retrieve(
        $this->request,
        new ApplicationContentQuery('core.users', 'visible record', 'en', 5),
    );

    expect($result->hits)->toHaveCount(1)
        ->and($this->provider->calls)->toBe(1)
        ->and($this->provider->capturedAuthorization?->permissionName)->toBe('default.users.select')
        ->and($this->provider->capturedAuthorization?->filters)->toBeNull();
});

it('uses the descriptor snapshot instead of re-reading mutable provider metadata', function (): void {
    $this->provider->source = new ApplicationContentSourceDescriptor(
        'missing.changed',
        'missing',
        'changed',
        ['en'],
        ['lexical'],
        ['changed_help'],
    );

    $result = $this->service->retrieve(
        $this->request,
        new ApplicationContentQuery('core.users', 'visible record', 'en', 5),
    );

    expect($result->source)->toBe('core.users')
        ->and($this->provider->calls)->toBe(1);
});

it('passes server-resolved row ACL filters to the provider', function (): void {
    $role = Role::factory()->create(['name' => 'limited_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($this->permission);
    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $this->permission->id,
        'filters' => new FiltersGroup([
            new Filter('users.id', [1], FilterOperator::In),
        ], WhereClause::And),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();
    $this->user->assignRole($role);
    Cache::flush();

    $this->service->retrieve(
        $this->request,
        new ApplicationContentQuery('core.users', 'visible record', 'en', 5),
    );

    expect($this->provider->capturedAuthorization?->filters)->toBeInstanceOf(FiltersGroup::class);
});

it('fails closed before provider execution for missing or mismatched identity', function (string $case): void {
    if ($case === 'missing') {
        Auth::logout();
        $this->request->setUserResolver(static fn (): null => null);
    } else {
        Auth::login(User::factory()->create());
    }

    expect(fn () => $this->service->retrieve(
        $this->request,
        new ApplicationContentQuery('core.users', 'visible record', 'en', 5),
    ))->toThrow(ApplicationContentUnavailableException::class)
        ->and($this->provider->calls)->toBe(0);
})->with(['missing', 'mismatched']);

it('fails closed before provider execution for unknown source or permission denial', function (string $case): void {
    $query = new ApplicationContentQuery('core.users', 'visible record', 'en', 5);

    if ($case === 'unknown') {
        $query = new ApplicationContentQuery('core.unknown', 'visible record', 'en', 5);
    } else {
        $denied = User::factory()->create();
        Auth::login($denied);
        $this->request->setUserResolver(static fn (): User => $denied);
    }

    expect(fn () => $this->service->retrieve($this->request, $query))
        ->toThrow(ApplicationContentUnavailableException::class)
        ->and($this->provider->calls)->toBe(0);
})->with(['unknown', 'denied']);

it('rejects a provider from a disabled module before execution', function (): void {
    $provider = new CapturingApplicationContentProvider(
        new ApplicationContentSourceDescriptor(
            'missing.records',
            'missing',
            'records',
            ['en'],
            ['lexical'],
            ['record_help'],
        ),
    );
    $registry = new ApplicationContentRetrievalProviderRegistry;
    $registry->register($provider);
    $service = new ApplicationContentRetrievalService(
        $registry,
        new AuthorizationService(new AclResolverService),
    );

    expect(fn () => $service->retrieve(
        $this->request,
        new ApplicationContentQuery('missing.records', 'visible record', 'en', 5),
    ))->toThrow(ApplicationContentUnavailableException::class)
        ->and($provider->calls)->toBe(0);
});

it('normalizes provider failures and invalid result invariants', function (string $case): void {
    if ($case === 'exception') {
        $this->provider->failure = new RuntimeException('Sensitive provider details');
    } elseif ($case === 'wrong-source') {
        $this->provider->result = new ApplicationContentResult('core.other', [], 'lexical', false);
    } else {
        $this->provider->result = new ApplicationContentResult(
            'core.users',
            array_map(applicationContentServiceHit(...), range(1, 6)),
            'lexical',
            false,
        );
    }

    expect(fn () => $this->service->retrieve(
        $this->request,
        new ApplicationContentQuery('core.users', 'visible record', 'en', 5),
    ))->toThrow(ApplicationContentUnavailableException::class, 'Application content is unavailable.');
})->with(['exception', 'wrong-source', 'oversized']);
