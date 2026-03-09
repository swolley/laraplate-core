<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Modules\Core\Listeners\AfterLoginListener;
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

        public function setRememberToken($value): void
        {
        }

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
