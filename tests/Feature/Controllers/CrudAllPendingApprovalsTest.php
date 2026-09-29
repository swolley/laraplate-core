<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Approvals\Operation;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;

function allPendingRequest(Setting $setting, User $author, string $md5): Modification
{
    return Modification::query()->create([
        'modifiable_type' => Setting::class,
        'modifiable_id' => $setting->getKey(),
        'modifier_id' => $author->getKey(),
        'modifier_type' => $author::class,
        'active' => true,
        'operation' => Operation::Update,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5($md5),
        'modifications' => ['is_public' => ['original' => false, 'modified' => true]],
    ]);
}

/**
 * ACLs hang off roles and bind only the roles holding their permission, so the approver holds
 * `approve` on settings and `select` on modifications through a role.
 */
function allPendingApprover(): User
{
    $role = Role::findOrCreate('settings_approver_' . uniqid(), 'web');

    foreach ([PermissionName::forModel(new Setting, 'approve'), PermissionName::forModel(new Modification, 'select')] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $role->givePermissionTo($permission);
    }

    $approver = User::factory()->create();
    $approver->assignRole($role);

    return $approver->refresh();
}

function allPendingAcl(Model $model, string $action, FiltersGroup $filters): void
{
    $permission = Permission::findOrCreate(PermissionName::forModel($model, $action), 'web');

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

beforeEach(function (): void {
    $this->setting = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'x', 'choices' => null]);
    $this->author = User::factory()->create();
    $this->other_author = User::factory()->create();
    $this->mine = allPendingRequest($this->setting, $this->author, 'mine');
    $this->theirs = allPendingRequest($this->setting, $this->other_author, 'theirs');
    allPendingRequest($this->setting, $this->author, 'decided')->update(['active' => false]);
});

it('lists every pending request an approver can vote on, across entities', function (): void {
    $approver = User::factory()->create();
    $approver->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    $rows = $this->actingAs($approver)->getJson(route('core.crud.pending-approvals.all'))->assertOk()->json('data');

    expect(collect($rows)->pluck('modification_id')->all())->toEqualCanonicalizing([$this->mine->id, $this->theirs->id])
        ->and($rows[0])->toMatchArray(['entity' => $this->setting->getTable(), 'operation' => 'update', 'can_vote' => true, 'is_mine' => false]);
});

it('lists only their own pending requests to an author who cannot vote', function (): void {
    $rows = $this->actingAs($this->author)->getJson(route('core.crud.pending-approvals.all'))->assertOk()->json('data');

    expect(collect($rows)->pluck('modification_id')->all())->toBe([$this->mine->id])
        ->and($rows[0])->toMatchArray(['is_mine' => true, 'can_vote' => false]);
});

it('never offers an approver a vote on their own request', function (): void {
    $permission = PermissionName::forModel(new Setting, 'approve');
    Permission::findOrCreate($permission, 'web');
    $this->author->givePermissionTo($permission);

    $rows = collect($this->actingAs($this->author)->getJson(route('core.crud.pending-approvals.all'))->assertOk()->json('data'))->keyBy('modification_id');

    expect($rows->keys()->all())->toEqualCanonicalizing([$this->mine->id, $this->theirs->id])
        ->and($rows[$this->mine->id]['can_vote'])->toBeFalse()
        ->and($rows[$this->theirs->id]['can_vote'])->toBeTrue();
});

it('restricts an approver to the records the table approve ACL lets them decide on', function (): void {
    $public = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'y', 'choices' => null, 'is_public' => true]);
    $on_public = allPendingRequest($public, $this->other_author, 'on-public');
    allPendingAcl(new Setting, 'approve', new FiltersGroup(filters: [
        new Filter('core_settings.is_public', true, FilterOperator::Equals),
    ]));

    $rows = $this->actingAs(allPendingApprover())->getJson(route('core.crud.pending-approvals.all'))->assertOk()->json('data');

    expect(collect($rows)->pluck('modification_id')->all())->toBe([$on_public->id]);
});

it('applies the modifications select ACL on top of what the permissions allow', function (): void {
    allPendingAcl(new Modification, 'select', new FiltersGroup(filters: [
        new Filter('core_modifications.modifier_id', $this->other_author->id, FilterOperator::Equals),
    ]));

    $rows = $this->actingAs(allPendingApprover())->getJson(route('core.crud.pending-approvals.all'))->assertOk()->json('data');

    expect(collect($rows)->pluck('modification_id')->all())->toBe([$this->theirs->id]);
});
