<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class, RefreshDatabase::class);

it('created sends verification email when config enabled and user unverified', function (): void {
    Notification::fake();
    config()->set('core.verify_new_user', true);

    $user = User::factory()->create(['email_verified_at' => null]);

    Notification::assertSentTo($user, Illuminate\Auth\Notifications\VerifyEmail::class);
});

it('created does not send verification email when config disabled', function (): void {
    Notification::fake();
    config()->set('core.verify_new_user', false);

    $user = User::factory()->create(['email_verified_at' => null]);

    Notification::assertNothingSent();
});

it('created does not send verification email when user already verified', function (): void {
    Notification::fake();
    config()->set('core.verify_new_user', true);

    $user = User::factory()->create(['email_verified_at' => now()]);

    Notification::assertNothingSent();
});

it('deleted observer clears license_id when licenses enabled', function (): void {
    config()->set('auth.enable_user_licenses', true);

    $license = License::factory()->create();
    $user = User::factory()->create(['license_id' => $license->id]);

    $observer = new Modules\Core\Observers\UserObserver();

    User::withoutEvents(function () use ($observer, $user): void {
        $observer->deleted($user);
    });

    expect($user->license_id)->toBeNull();
});

it('deleted observer skips when licenses disabled', function (): void {
    config()->set('auth.enable_user_licenses', false);

    $license = License::factory()->create();
    $user = User::factory()->create(['license_id' => $license->id]);

    $observer = new Modules\Core\Observers\UserObserver();
    $observer->deleted($user);

    expect($user->license_id)->toBe($license->id);
});
