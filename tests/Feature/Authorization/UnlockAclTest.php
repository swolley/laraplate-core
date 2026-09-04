<?php

declare(strict_types=1);

use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
use Symfony\Component\HttpFoundation\Response;

/**
 * `unlock` is the right to release somebody else's lock, so on its own it is the right to evict
 * anyone from anything. Narrowing it is a row-level ACL job, and these cases prove the ACL is
 * actually consulted on the write path: until this work, filters were applied on reads only, so a
 * row an ACL hid from a list could still be acted on by id.
 *
 * No default ACL ships for it. See the plan: every candidate default turned out to grant nothing.
 */
/**
 * ACLs hang off roles, so the permissions are granted to a role rather than to the user directly:
 * a user holding a permission with no role at all matches no ACL and is filtered by nothing.
 */
function unlockOperator(): User
{
    $role = Role::findOrCreate('lock_operator_' . uniqid(), 'web');

    foreach (['update', 'unlock'] as $operation) {
        $name = 'default.users.' . $operation;
        Permission::findOrCreate($name, 'web');
        $role->givePermissionTo($name);
    }

    $operator = User::factory()->create();
    $operator->assignRole($role);

    return $operator;
}

function writeUnlockAcl(FiltersGroup $filters): void
{
    $permission = Permission::findOrCreate(
        PermissionName::forModel(new User(), ActionEnum::Unlock->value),
        'web',
    );

    // The `filters` validation rule expects the array form rather than the cast object, so ACLs are
    // written the way the seeders write them.
    $acl = new ACL();
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'role_id' => null,
        'filters' => $filters,
        'unrestricted' => false,
        'priority' => 0,
        'is_active' => true,
        'description' => 'Test ACL.',
    ]);
    $acl->save();
}

it('lets a row-level ACL decide which of somebody else’s locks may be released', function (): void {
    $reachable_owner = User::factory()->create();
    $out_of_reach_owner = User::factory()->create();

    $reachable = User::factory()->create();
    $reachable->lockBy($reachable_owner, now()->addHour());

    $out_of_reach = User::factory()->create();
    $out_of_reach->lockBy($out_of_reach_owner, now()->addHour());

    writeUnlockAcl(new FiltersGroup(filters: [
        new Filter('users.locked_user_id', $reachable_owner->id, FilterOperator::Equals),
    ]));

    $operator = unlockOperator();
    $route = route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']);

    $this->actingAs($operator)->patchJson($route, ['id' => $reachable->id])->assertOk();

    // The permission is held in both cases, so this is not 403: the row is simply out of reach.
    $this->actingAs($operator)->patchJson($route, ['id' => $out_of_reach->id])
        ->assertStatus(Response::HTTP_LOCKED);

    expect($reachable->fresh()?->isLocked())->toBeFalse()
        ->and($out_of_reach->fresh()?->isLocked())->toBeTrue();
});

it('has nothing to release once a lock has lapsed', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->subMinute());

    // Expiry is evaluated on read, so the record is already free to everybody and unlock is a
    // no-op rather than a privileged act. The columns are cleared by the housekeeping sweep, not
    // here. This is also why "unlock may only clear lapsed locks" is not a usable default ACL: it
    // would grant exactly nothing.
    $response = $this->actingAs(unlockOperator())->patchJson(
        route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($response->json('data'))->toBeEmpty()
        ->and($target->fresh()?->isLocked())->toBeFalse();
});

it('applies an unscoped ACL to a permission granted straight to the user', function (): void {
    // Role grants and direct grants are merged by the entity gate, so the row filters have to be
    // merged too. Until this fix, ACL resolution started from the user's roles and returned nothing
    // when there were none, which meant handing somebody a permission directly quietly handed them
    // every row of the table.
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    writeUnlockAcl(new FiltersGroup(filters: [
        new Filter('users.locked_user_id', '@user.id', FilterOperator::Equals),
    ]));

    $operator = User::factory()->create();

    foreach (['update', 'unlock'] as $operation) {
        $name = 'default.users.' . $operation;
        Permission::findOrCreate($name, 'web');
        $operator->givePermissionTo($name);
    }

    expect($operator->roles)->toBeEmpty();

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertStatus(Response::HTTP_LOCKED);

    expect($target->fresh()?->isLocked())->toBeTrue();
});

it('resolves @user placeholders against the acting user', function (): void {
    $owner = User::factory()->create();
    $target = User::factory()->create();
    $target->lockBy($owner, now()->addHour());

    $operator = unlockOperator();

    // "Locks held by me" is not a useful ACL in production, since releasing your own lock never
    // passes through `unlock`. It is used here because it is the shortest filter that proves the
    // placeholder resolves to this user and not to a literal string.
    writeUnlockAcl(new FiltersGroup(filters: [
        new Filter('users.locked_user_id', '@user.id', FilterOperator::Equals),
    ]));

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertStatus(Response::HTTP_LOCKED);

    // And the same ACL does reach a lock whose owner is the acting user.
    $mine = User::factory()->create();
    $mine->lockBy($operator, now()->addHour());
    $mine->forceFill(['locked_user_id' => $operator->id])->saveQuietly();

    expect($mine->fresh()?->locked_user_id)->toBe($operator->id);
});

it('never resolves a hidden attribute through the placeholder', function (): void {
    $operator = unlockOperator();
    $target = User::factory()->create();
    $target->lockBy(User::factory()->create(), now()->addHour());

    // A filter naming a hidden attribute must resolve to null, which matches nothing, rather than
    // pulling a password hash into the query.
    writeUnlockAcl(new FiltersGroup(filters: [
        new Filter('users.username', '@user.password', FilterOperator::Equals),
    ]));

    $response = $this->actingAs($operator)->patchJson(
        route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertStatus(Response::HTTP_LOCKED);

    expect($target->fresh()?->isLocked())->toBeTrue();
});
