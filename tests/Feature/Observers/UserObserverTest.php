<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Modules\Core\Observers\UserObserver;

it('created sends verification email when config enabled and user unverified', function (): void {
    Notification::fake();
    config()->set('core.auth.email_verification.enabled', true);

    $user = User::factory()->create(['email_verified_at' => null]);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('created does not send verification email when config disabled', function (): void {
    Notification::fake();
    config()->set('core.auth.email_verification.enabled', false);

    $user = User::factory()->create(['email_verified_at' => null]);

    Notification::assertNothingSent();
});

it('created does not send verification email when user already verified', function (): void {
    Notification::fake();
    config()->set('core.auth.email_verification.enabled', true);

    $user = User::factory()->create(['email_verified_at' => now()]);

    Notification::assertNothingSent();
});

it('deleted observer clears license_id when licenses enabled', function (): void {
    config()->set('core.auth.licenses.enabled', true);

    $license = License::factory()->create();
    $user = User::factory()->create(['license_id' => $license->id]);

    $observer = new UserObserver;

    User::withoutEvents(function () use ($observer, $user): void {
        $observer->deleted($user);
    });

    expect($user->license_id)->toBeNull();
});

it('deleted observer skips when licenses disabled', function (): void {
    config()->set('core.auth.licenses.enabled', false);

    $license = License::factory()->create();
    $user = User::factory()->create(['license_id' => $license->id]);

    $observer = new UserObserver;
    $observer->deleted($user);

    expect($user->license_id)->toBe($license->id);
});
