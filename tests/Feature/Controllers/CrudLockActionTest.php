<?php

declare(strict_types=1);

use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * The lock route carries three different acts. A **lease** is what an edit form takes: owned,
 * expiring, and gated by nothing more than `update`. A **hold** is an owned lock with no deadline
 * and a **freeze** is an ownerless one, and both are deliberate acts gated by `lock`. Releasing
 * somebody else's lock is a fourth right again, `unlock`, because being trusted to freeze a record
 * and being trusted to unblock other people are not the same responsibility.
 */
function crudLockRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'users'];
}

function crudLockOperator(string ...$operations): User
{
    $operator = User::factory()->create();

    foreach ($operations as $operation) {
        $name = 'default.users.' . $operation;
        Permission::findOrCreate($name, 'web');
        $operator->givePermissionTo($name);
    }

    return $operator;
}

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->actor->assignRole(Role::findOrCreate('superadmin', 'web'));
    $this->actingAs($this->actor);
});

it('takes a lease owned by the caller, with a deadline', function (): void {
    $target = User::factory()->create();

    $response = $this->patchJson(route('core.crud.lock', crudLockRouteParams()), ['id' => $target->id]);

    $response->assertOk();

    $fresh = $target->fresh();

    expect($fresh?->isLocked())->toBeTrue()
        ->and($fresh?->locked_user_id)->toBe($this->actor->id)
        ->and($fresh?->locked_until)->not->toBeNull();
});

it('writes nothing when the caller re-locks a record it already holds', function (): void {
    $target = User::factory()->create();
    $target->lockBy($this->actor, now()->addHour());

    $deadline_before = $target->fresh()?->locked_until;

    $response = $this->patchJson(route('core.crud.lock', crudLockRouteParams()), ['id' => $target->id]);

    // 200 with nothing affected, not 304: the answer carries a body, and the empty collection is
    // already the "nothing changed" signal.
    $response->assertOk();

    expect($response->json('data'))->toBeEmpty()
        ->and($target->fresh()?->locked_until?->equalTo($deadline_before))->toBeTrue();
});

it('refuses to lock a record leased by somebody else, and names the holder', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    $response = $this->patchJson(route('core.crud.lock', crudLockRouteParams()), ['id' => $target->id]);

    $response->assertStatus(Response::HTTP_LOCKED);

    expect((string) $response->json('error'))->toContain((string) $owner->id)
        ->and($target->fresh()?->locked_user_id)->toBe($owner->id);
});

it('refuses to lock a frozen record', function (): void {
    $target = User::factory()->create();
    $target->lock();

    $response = $this->patchJson(route('core.crud.lock', crudLockRouteParams()), ['id' => $target->id]);

    $response->assertStatus(Response::HTTP_LOCKED);

    expect((string) $response->json('error'))->toContain('frozen');
});

it('treats a lapsed lock as free', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->subMinute());

    // No sweep has run: the row still carries the other user's lock.
    expect($target->fresh()?->locked_user_id)->toBe($owner->id);

    $response = $this->patchJson(route('core.crud.lock', crudLockRouteParams()), ['id' => $target->id]);

    $response->assertOk();

    expect($target->fresh()?->locked_user_id)->toBe($this->actor->id);
});

it('lets the owner move the deadline of its own lock, in either direction', function (): void {
    $target = User::factory()->create();
    $target->lockBy($this->actor, now()->addDay());

    $shorter = now()->addMinutes(5);

    $response = $this->patchJson(route('core.crud.lock', crudLockRouteParams()), [
        'id' => $target->id,
        'locked_until' => $shorter->toIso8601String(),
    ]);

    $response->assertOk();

    expect($target->fresh()?->locked_until?->diffInSeconds($shorter, true))->toBeLessThan(2);
});

it('gives a lease, never a freeze, when no freeze is asked for', function (): void {
    $operator = crudLockOperator('update', 'lock');
    $target = User::factory()->create();

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.lock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertOk();

    // Holding `lock` does not turn the ordinary edit lifecycle into a freeze.
    expect($target->fresh()?->locked_user_id)->toBe($operator->id);
});

it('needs the lock permission to freeze a record', function (): void {
    $operator = crudLockOperator('update');
    $target = User::factory()->create();

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.lock', crudLockRouteParams()),
        ['id' => $target->id, 'freeze' => true],
    );

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);

    expect($target->fresh()?->isLocked())->toBeFalse();
});

it('freezes without an owner when the lock permission is held', function (): void {
    $operator = crudLockOperator('update', 'lock');
    $target = User::factory()->create();

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.lock', crudLockRouteParams()),
        ['id' => $target->id, 'freeze' => true],
    );

    $response->assertOk();

    $fresh = $target->fresh();

    expect($fresh?->isLocked())->toBeTrue()
        ->and($fresh?->locked_user_id)->toBeNull();
});

it('needs the lock permission for an owned lock with no deadline', function (): void {
    $operator = crudLockOperator('update');
    $target = User::factory()->create();

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.lock', crudLockRouteParams()),
        ['id' => $target->id, 'locked_until' => null],
    );

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);
});

it('lets a user release its own lock without the unlock permission', function (): void {
    $operator = crudLockOperator('update');
    $target = User::factory()->create();
    $target->lockBy($operator, now()->addHour());

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($target->fresh()?->isLocked())->toBeFalse();
});

it('needs the unlock permission to release somebody else’s lock', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    // `lock` is deliberately not enough: freezing records and unblocking colleagues are different
    // responsibilities and often different people.
    $operator = crudLockOperator('update', 'lock');

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertStatus(Response::HTTP_UNAUTHORIZED);

    expect($target->fresh()?->isLocked())->toBeTrue();
});

it('releases somebody else’s lock when the unlock permission is held', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    $operator = crudLockOperator('update', 'unlock');

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($target->fresh()?->isLocked())->toBeFalse();
});

it('writes nothing when unlocking a record that is not locked', function (): void {
    $target = User::factory()->create();

    $response = $this->patchJson(route('core.crud.unlock', crudLockRouteParams()), ['id' => $target->id]);

    $response->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('refuses an update on a record somebody else has taken charge of', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    // The whole point of a lease. Until the guard was wired up this went straight through, and the
    // lock protected nothing outside the four ERP tables carrying database triggers.
    $response = $this->patchJson(
        route('core.crud.replace', crudLockRouteParams()),
        ['id' => $target->id, 'name' => 'edited by somebody else'],
    );

    $response->assertStatus(Response::HTTP_LOCKED);

    expect($target->fresh()?->name)->not->toBe('edited by somebody else');
});

it('lets the holder update the record it has taken charge of', function (): void {
    $target = User::factory()->create();
    $target->lockBy($this->actor, now()->addHour());

    $response = $this->patchJson(
        route('core.crud.replace', crudLockRouteParams()),
        ['id' => $target->id, 'name' => 'edited by the holder'],
    );

    $response->assertOk();

    expect($target->fresh()?->name)->toBe('edited by the holder');
});

