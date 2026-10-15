<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Console\ResetUserPasswordCommand;
use Modules\Core\Models\User;
use Symfony\Component\Console\Command\Command as BaseCommand;

beforeEach(function (): void {
    config(['auth.providers.users.model' => User::class]);

    /** @var Illuminate\Contracts\Console\Kernel $kernel */
    $kernel = app(Illuminate\Contracts\Console\Kernel::class);

    if (! array_key_exists('auth:reset-password', $kernel->all())) {
        $kernel->registerCommand(app(ResetUserPasswordCommand::class));
    }
});

/**
 * Drive the search prompt fallback to pick the given user.
 */
function pickUserToReset(Illuminate\Testing\PendingCommand $command, User $user): Illuminate\Testing\PendingCommand
{
    return $command
        ->expectsQuestion('User', $user->email)
        ->expectsChoice('User', (string) $user->getKey(), [(string) $user->getKey() => sprintf('%s (%s)', $user->email, $user->username)]);
}

it('sets the typed password and rotates the remember token', function (): void {
    $user = User::factory()->create(['remember_token' => 'old-token']);

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsConfirmation(sprintf('Reset the password of %s?', $user->email), 'yes')
        ->expectsChoice('How do you want to reset it?', 'type', ['type' => 'Enter a new password', 'email' => 'Send a password reset email'])
        ->expectsQuestion('New password', 'N3w-Strong-Password!')
        ->expectsQuestion('Confirm password', 'N3w-Strong-Password!')
        ->assertExitCode(BaseCommand::SUCCESS);

    $user->refresh();

    expect(Hash::check('N3w-Strong-Password!', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-token');
});

it('resets the password of a locked user and keeps the lock', function (): void {
    $user = User::factory()->create();
    $user->lock(User::factory()->create());

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsConfirmation(sprintf('Reset the password of %s?', $user->email), 'yes')
        ->expectsChoice('How do you want to reset it?', 'type', ['type' => 'Enter a new password', 'email' => 'Send a password reset email'])
        ->expectsQuestion('New password', 'N3w-Strong-Password!')
        ->expectsQuestion('Confirm password', 'N3w-Strong-Password!')
        ->assertExitCode(BaseCommand::SUCCESS);

    $user->refresh();

    expect(Hash::check('N3w-Strong-Password!', $user->password))->toBeTrue()
        ->and($user->isLocked())->toBeTrue();
});

it('generates and prints a random password when left blank', function (): void {
    $user = User::factory()->create();
    $old_hash = $user->password;

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsConfirmation(sprintf('Reset the password of %s?', $user->email), 'yes')
        ->expectsChoice('How do you want to reset it?', 'type', ['type' => 'Enter a new password', 'email' => 'Send a password reset email'])
        ->expectsQuestion('New password', '')
        ->expectsOutputToContain('Generated password:')
        ->assertExitCode(BaseCommand::SUCCESS);

    expect($user->refresh()->password)->not->toBe($old_hash);
});

it('rejects a password that breaks the password rules', function (): void {
    $user = User::factory()->create();
    $old_hash = $user->password;

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsConfirmation(sprintf('Reset the password of %s?', $user->email), 'yes')
        ->expectsChoice('How do you want to reset it?', 'type', ['type' => 'Enter a new password', 'email' => 'Send a password reset email'])
        ->expectsQuestion('New password', 'a')
        ->assertExitCode(BaseCommand::FAILURE);

    expect($user->refresh()->password)->toBe($old_hash);
});

it('sends the password reset email with a broker token', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $old_hash = $user->password;

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsConfirmation(sprintf('Reset the password of %s?', $user->email), 'yes')
        ->expectsChoice('How do you want to reset it?', 'email', ['type' => 'Enter a new password', 'email' => 'Send a password reset email'])
        ->assertExitCode(BaseCommand::SUCCESS);

    Notification::assertSentTo($user, ResetPassword::class, static fn (ResetPassword $notification): bool => $notification->token !== $user->email);
    expect($user->refresh()->password)->toBe($old_hash);
});

it('changes nothing when the operator does not confirm', function (): void {
    $user = User::factory()->create();
    $old_hash = $user->password;

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsConfirmation(sprintf('Reset the password of %s?', $user->email), 'no')
        ->assertExitCode(BaseCommand::SUCCESS);

    expect($user->refresh()->password)->toBe($old_hash);
});

it('refuses a service account', function (): void {
    $user = User::factory()->serviceAccount()->create();
    $old_hash = $user->password;

    pickUserToReset($this->artisan('auth:reset-password'), $user)
        ->expectsOutputToContain('Service accounts have no usable password')
        ->assertExitCode(BaseCommand::FAILURE);

    expect($user->refresh()->password)->toBe($old_hash);
});
