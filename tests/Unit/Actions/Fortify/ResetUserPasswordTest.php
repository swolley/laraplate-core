<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\Fortify\ResetUserPassword;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('resets user password with valid input', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old')]);
    $action = new ResetUserPassword();

    $action->reset($user, [
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $user->refresh();
    expect(Hash::check('NewSecurePassword123!', $user->password))->toBeTrue()
        ->and(Hash::check('old', $user->password))->toBeFalse();
});

it('throws validation exception when password does not match confirmation', function (): void {
    $user = User::factory()->create();
    $action = new ResetUserPassword();

    expect(fn () => $action->reset($user, [
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'DifferentPassword123!',
    ]))->toThrow(ValidationException::class);
});

it('throws validation exception when password is too weak', function (): void {
    $user = User::factory()->create();
    $action = new ResetUserPassword();

    expect(fn () => $action->reset($user, [
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->toThrow(ValidationException::class);
});
