<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\Fortify\UpdateUserPassword;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('updates user password when current password is correct', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CurrentPassword123!')]);
    Auth::login($user);

    $action = new UpdateUserPassword();
    $action->update($user, [
        'current_password' => 'CurrentPassword123!',
        'password' => 'NewSecurePassword456!',
        'password_confirmation' => 'NewSecurePassword456!',
    ]);

    $user->refresh();
    expect(Hash::check('NewSecurePassword456!', $user->password))->toBeTrue()
        ->and(Hash::check('CurrentPassword123!', $user->password))->toBeFalse();
});

it('throws validation exception when current password is wrong', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CurrentPassword123!')]);
    Auth::login($user);

    $action = new UpdateUserPassword();

    expect(fn () => $action->update($user, [
        'current_password' => 'WrongPassword',
        'password' => 'NewSecurePassword456!',
        'password_confirmation' => 'NewSecurePassword456!',
    ]))->toThrow(ValidationException::class);
});

it('throws validation exception when new password does not match confirmation', function (): void {
    $user = User::factory()->create(['password' => Hash::make('CurrentPassword123!')]);
    Auth::login($user);

    $action = new UpdateUserPassword();

    expect(fn () => $action->update($user, [
        'current_password' => 'CurrentPassword123!',
        'password' => 'NewSecurePassword456!',
        'password_confirmation' => 'DifferentConfirmation',
    ]))->toThrow(ValidationException::class);
});
