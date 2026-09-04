<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\EditUser;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

/**
 * Opening a Filament edit page is a statement of intent — viewing has its own page — so it takes an
 * editorial lease. There is no reliable "left the page" hook in the panel, so nothing releases the
 * lease explicitly: its deadline does, and reopening the page takes a fresh one.
 *
 * When somebody else already holds the record the page does not decide for the user: it asks,
 * offering read-only or a way back to the list.
 */
function editPageActor(): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($actor);
    Filament::setCurrentPanel('admin');

    return $actor;
}

it('takes a lease for the user opening the edit page', function (): void {
    $actor = editPageActor();
    $target = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSet('record_is_read_only', false);

    $fresh = $target->fresh();

    expect($fresh?->isLockedBy($actor))->toBeTrue()
        ->and($fresh?->locked_until)->not->toBeNull();
});

it('opens read-only, without stealing the lease, when somebody else holds the record', function (): void {
    editPageActor();

    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSet('record_is_read_only', true);

    expect($target->fresh()?->locked_user_id)->toBe($owner->id);
});

it('opens read-only on a frozen record', function (): void {
    editPageActor();

    $target = User::factory()->create();
    $target->lock();

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSet('record_is_read_only', true);

    expect($target->fresh()?->locked_user_id)->toBeNull()
        ->and($target->fresh()?->isLocked())->toBeTrue();
});

it('reopens on a lease of its own without treating it as somebody else’s', function (): void {
    $actor = editPageActor();

    $target = User::factory()->create();
    $target->lockBy($actor, now()->addHour());

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->assertSet('record_is_read_only', false);

    expect($target->fresh()?->isLockedBy($actor))->toBeTrue();
});
