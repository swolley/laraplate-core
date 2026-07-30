<?php

declare(strict_types=1);

use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

/**
 * @return array{module: string, entity: string}
 */
function crudLockRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'users'];
}

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->actor->assignRole(Role::findOrCreate('superadmin', 'web'));
    $this->actingAs($this->actor);
});

it('locks an unlocked record through the crud lock route', function (): void {
    $target = User::factory()->create();

    $response = $this->patchJson(
        route('core.crud.lock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($target->fresh()?->isLocked())->toBeTrue();
});

it('rejects locking a record that is already locked', function (): void {
    $target = User::factory()->create();
    $target->lock();

    $response = $this->patchJson(
        route('core.crud.lock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertStatus(Symfony\Component\HttpFoundation\Response::HTTP_NOT_MODIFIED);
});

it('unlocks a locked record through the crud unlock route', function (): void {
    $target = User::factory()->create();
    $target->lock();

    $response = $this->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($target->fresh()?->isLocked())->toBeFalse();
});

it('rejects unlocking a record that is not locked', function (): void {
    $target = User::factory()->create();

    $response = $this->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertStatus(Symfony\Component\HttpFoundation\Response::HTTP_NOT_MODIFIED);
});

it('governs unlock with the lock permission', function (): void {
    $target = User::factory()->create();
    $target->lock();

    $operator = User::factory()->create();
    Permission::findOrCreate('default.users.lock', 'web');
    $operator->givePermissionTo('default.users.lock');

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($target->fresh()?->isLocked())->toBeFalse();
});

it('denies unlock to a user without the lock permission', function (): void {
    $target = User::factory()->create();
    $target->lock();

    $operator = User::factory()->create();

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', crudLockRouteParams()),
        ['id' => $target->id],
    );

    $response->assertStatus(Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);

    expect($target->fresh()?->isLocked())->toBeTrue();
});
