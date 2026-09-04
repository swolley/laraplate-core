<?php

declare(strict_types=1);

use Modules\Core\Models\User;

/**
 * The sweep is housekeeping: everything it does is already true on read. What matters is that it
 * clears exactly the lapsed locks and leaves every live one alone.
 */
function lockUserUntil(User $user, ?DateTimeInterface $until, ?User $owner = null): User
{
    $user->lock($owner, $until);

    return $user;
}

it('releases lapsed locks and leaves live ones alone', function (): void {
    $owner = User::factory()->create();

    $expired_lease = lockUserUntil(User::factory()->create(), now()->subMinute(), $owner);
    $live_lease = lockUserUntil(User::factory()->create(), now()->addHour(), $owner);
    $expired_freeze = lockUserUntil(User::factory()->create(), now()->subMinute());
    $permanent_freeze = lockUserUntil(User::factory()->create(), null);

    $this->artisan('model:lock-sweep')->assertSuccessful();

    expect($expired_lease->fresh()->locked_at)->toBeNull()
        ->and($expired_lease->fresh()->locked_user_id)->toBeNull()
        ->and($expired_lease->fresh()->locked_until)->toBeNull()
        ->and($expired_freeze->fresh()->locked_at)->toBeNull()
        ->and($live_lease->fresh()->locked_at)->not->toBeNull()
        ->and($live_lease->fresh()->locked_user_id)->toBe($owner->id)
        ->and($permanent_freeze->fresh()->locked_at)->not->toBeNull();
});

it('does not touch updated_at when it releases a lapsed lock', function (): void {
    $owner = User::factory()->create();
    $target = lockUserUntil(User::factory()->create(), now()->subMinute(), $owner);

    $updated_before = $target->fresh()->updated_at;

    $this->travelTo(now()->addMinutes(10));
    $this->artisan('model:lock-sweep')->assertSuccessful();

    $fresh = $target->fresh();

    expect($fresh->locked_at)->toBeNull()
        ->and($fresh->updated_at->equalTo($updated_before))->toBeTrue();
});

it('clears no more than the requested number of rows per model', function (): void {
    $owner = User::factory()->create();

    $targets = collect(range(1, 3))
        ->map(fn (): User => lockUserUntil(User::factory()->create(), now()->subMinute(), $owner));

    $this->artisan('model:lock-sweep', ['--limit' => 1])->assertSuccessful();

    $still_locked = $targets->filter(fn (User $user): bool => $user->fresh()->locked_at !== null);

    expect($still_locked)->toHaveCount(2);
});
