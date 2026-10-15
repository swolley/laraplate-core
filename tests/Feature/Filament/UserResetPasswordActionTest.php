<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\ListUsers;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    HttpContext::panelActorWithoutApproval(new User, ['select', 'update']);
});

it('sends the reset email with a broker token, not the email address', function (): void {
    Notification::fake();
    $user = App\Models\User::query()->create(User::factory()->raw());

    Livewire::test(ListUsers::class)
        ->callTableAction('reset_password', $user)
        ->assertNotified('Password reset email has been sent.');

    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        static fn (ResetPassword $notification): bool => $notification->token !== $user->email
            && Password::broker()->tokenExists($user, $notification->token),
    );
});

it('reports the broker refusal when a link was sent moments ago', function (): void {
    Notification::fake();
    $user = App\Models\User::query()->create(User::factory()->raw());
    Password::broker()->sendResetLink(['email' => $user->email]);

    Livewire::test(ListUsers::class)
        ->callTableAction('reset_password', $user)
        ->assertNotified(__(Password::RESET_THROTTLED));

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('hides the action on a service account', function (): void {
    $service_account = App\Models\User::query()->create(User::factory()->serviceAccount()->raw());

    Livewire::test(ListUsers::class)->assertTableActionHidden('reset_password', $service_account);
});
