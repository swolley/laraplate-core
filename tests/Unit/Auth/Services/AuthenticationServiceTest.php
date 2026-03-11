<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as BaseUser;
use Illuminate\Http\Request;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Tests\TestCase;

uses(TestCase::class);

class FakeAuthUser extends BaseUser
{
    protected $fillable = ['name', 'email'];
}

class FakeEnabledProvider implements IAuthenticationProvider
{
    public bool $handleCalled = false;

    public function __construct(private readonly string $name) {}

    public function canHandle(Request $request): bool
    {
        return $request->attributes->get('provider') === $this->name;
    }

    public function authenticate(Request $request): array
    {
        $this->handleCalled = true;

        $user = new FakeAuthUser(['name' => 'John', 'email' => 'john@example.com']);

        return [
            'success' => true,
            'user' => $user,
            'error' => null,
            'license' => null,
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return $this->name;
    }
}

class FakeDisabledProvider extends FakeEnabledProvider
{
    public function isEnabled(): bool
    {
        return false;
    }
}

it('returns first successful authentication result from enabled providers', function (): void {
    $request = new Request();
    $request->attributes->set('provider', 'first');

    $disabled = new FakeDisabledProvider('disabled');
    $first = new FakeEnabledProvider('first');
    $second = new FakeEnabledProvider('second');

    $service = new AuthenticationService([$disabled, $first, $second]);

    $result = $service->authenticate($request);

    expect($result['success'])->toBeTrue()
        ->and($result['user'])->toBeInstanceOf(FakeAuthUser::class)
        ->and($result['error'])->toBeNull()
        ->and($result['license'])->toBeNull()
        ->and($first->handleCalled)->toBeTrue()
        ->and($second->handleCalled)->toBeFalse();
});

it('falls back to error structure when no provider can handle the request', function (): void {
    $request = new Request();
    $request->attributes->set('provider', 'unknown');

    $p1 = new FakeEnabledProvider('first');
    $p2 = new FakeDisabledProvider('second');

    $service = new AuthenticationService([$p1, $p2]);

    $result = $service->authenticate($request);

    expect($result)->toMatchArray([
        'success' => false,
        'user' => null,
        'error' => 'No suitable authentication provider found',
        'license' => null,
    ]);
});

it('getAvailableProviders returns names of enabled providers only', function (): void {
    $p1 = new FakeEnabledProvider('first');
    $p2 = new FakeDisabledProvider('second');
    $p3 = new FakeEnabledProvider('third');

    $service = new AuthenticationService([$p1, $p2, $p3]);

    $providers = $service->getAvailableProviders();

    expect(array_values($providers))->toBe(['first', 'third']);
});

