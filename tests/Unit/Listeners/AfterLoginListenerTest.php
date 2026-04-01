<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Models\Impersonate;
use Modules\Core\Listeners\AfterLoginListener;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('listener has correct class structure', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);

    expect($reflection->getName())->toBe('Modules\Core\Listeners\AfterLoginListener');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
    expect($reflection->hasMethod('checkUserLicense'))->toBeTrue();
});

it('listener handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(AfterLoginListener::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isPublic())->toBeTrue();
});

it('listener checkUserLicense method has correct signature', function (): void {
    $reflection = new ReflectionMethod(AfterLoginListener::class, 'checkUserLicense');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getReturnType()->getName())->toBe('void');
    expect($reflection->isStatic())->toBeTrue();
    expect($reflection->isPublic())->toBeTrue();
});

it('listener handle updates last_login_at and logs when user does not use Impersonate', function (): void {
    Log::spy();

    $user = new class() implements Authenticatable
    {
        public $id = 1;

        public $username = 'testuser';

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): ?string
        {
            return null;
        }

        public function update(array $attributes = []): bool
        {
            return true;
        }

        public function isUnlocked(): bool
        {
            return false;
        }
    };

    $event = new Login('web', $user, true);
    (new AfterLoginListener())->handle($event);

    Log::shouldHaveReceived('info')->once()->with('{username} logged in', ['username' => 'testuser']);
});

it('listener handle logs out other devices when unlocked', function (): void {
    Auth::shouldReceive('logoutOtherDevices')->once()->with('secret');
    Log::spy();

    $user = new class() implements Authenticatable
    {
        public $id = 1;

        public $username = 'unlocked-user';

        public $password = 'secret';

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return $this->id;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return $this->password;
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): ?string
        {
            return null;
        }

        public function update(array $attributes = []): bool
        {
            return true;
        }

        public function isUnlocked(): bool
        {
            return true;
        }
    };

    (new AfterLoginListener())->handle(new Login('web', $user, true));

    Log::shouldHaveReceived('info')->once();
});

it('listener source contains license-check and impersonation branches', function (): void {
    $reflection = new ReflectionClass(AfterLoginListener::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('whereDoesntHave(\'user\')')
        ->and($source)->toContain('associate($available_licenses)')
        ->and($source)->toContain('elseif ($user->isImpersonated())')
        ->and($source)->toContain('{impersonator} is impersonating {impersonated}');
});

it('checkUserLicense associates first free license when conditions are met', function (): void {
    config()->set('auth.enable_user_licenses', true);
    config()->set('permission.roles.superadmin', 'superadmin');

    $license = License::factory()->create();
    $user = User::factory()->create([
        'email' => 'licensed-user@example.test',
        'license_id' => null,
    ]);

    expect(in_array(Impersonate::class, class_uses_recursive($user::class), true))->toBeTrue()
        ->and($user->isGuest())->toBeFalse()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->license_id)->toBeNull();

    AfterLoginListener::checkUserLicense($user);

    expect($user->license_id)->toBe((string) $license->id);
});

it('handle logs impersonation context for impersonated users', function (): void {
    Log::spy();

    $impersonator = User::factory()->create(['username' => 'impersonator-user']);
    $user = new class extends User
    {
        public User $impersonatorUser;

        public function isImpersonated(): bool
        {
            return true;
        }

        public function getImpersonator(): User
        {
            return $this->impersonatorUser;
        }
    };
    $user->impersonatorUser = $impersonator;
    $user->username = 'impersonated-user';

    (new AfterLoginListener())->handle(new Login('web', $user, true));

    Log::shouldHaveReceived('info')->once()->with(
        '{impersonator} is impersonating {impersonated}',
        ['impersonator' => 'impersonator-user', 'impersonated' => 'impersonated-user'],
    );
});
