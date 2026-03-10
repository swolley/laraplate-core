<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\Fortify\UpdateUserProfileInformation;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('updates user name and email when email unchanged', function (): void {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'user@example.com',
        'email_verified_at' => now(),
    ]);

    $action = new UpdateUserProfileInformation();
    $action->update($user, [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'user@example.com',
    ]);

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('user@example.com')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('nulls email_verified_at and updates when email changed for MustVerifyEmail user', function (): void {
    $user = User::factory()->create([
        'name' => 'User',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $user = \Mockery::mock($user)->makePartial();
    $user->shouldReceive('sendEmailVerificationNotification')->once();

    $action = new UpdateUserProfileInformation();
    $action->update($user, [
        'id' => $user->id,
        'name' => 'User',
        'email' => 'new@example.com',
    ]);

    $fresh = User::find($user->getKey());
    expect($fresh->email)->toBe('new@example.com')
        ->and($fresh->email_verified_at)->toBeNull();
});

it('updates only name when input has no email change', function (): void {
    $user = User::factory()->create([
        'name' => 'Original',
        'email' => 'original@example.com',
    ]);

    $action = new UpdateUserProfileInformation();
    $action->update($user, [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'original@example.com',
    ]);

    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('original@example.com');
});

it('throws validation exception when email is invalid', function (): void {
    $user = User::factory()->create(['email' => 'valid@example.com']);
    $action = new UpdateUserProfileInformation();

    expect(fn () => $action->update($user, [
        'id' => $user->id,
        'name' => 'User',
        'email' => 'not-an-email',
    ]))->toThrow(ValidationException::class);
});

it('throws validation exception when name exceeds max length', function (): void {
    $user = User::factory()->create();
    $action = new UpdateUserProfileInformation();

    expect(fn () => $action->update($user, [
        'id' => $user->id,
        'name' => str_repeat('a', 256),
        'email' => $user->email,
    ]))->toThrow(ValidationException::class);
});
