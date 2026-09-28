<?php

declare(strict_types=1);

use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;

/**
 * @return array{module: string, entity: string}
 */
function crudVoteRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

/**
 * @param  array<string, array{original: mixed, modified: mixed}>  $diff
 */
function crudVotePendingUpdate(Setting $setting, User $author, array $diff): Modification
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
        'md5' => md5(json_encode($diff, JSON_THROW_ON_ERROR)),
        'modifications' => $diff,
    ]);
}

beforeEach(function (): void {
    $this->setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'type' => 'string',
        'value' => 'x',
        'choices' => null,
        'is_public' => false,
        'description' => 'before',
    ]);
    $author = User::factory()->create();
    $this->public_request = crudVotePendingUpdate($this->setting, $author, ['is_public' => ['original' => false, 'modified' => true]]);
    $this->description_request = crudVotePendingUpdate($this->setting, $author, ['description' => ['original' => 'before', 'modified' => 'after']]);
    $this->third_request = crudVotePendingUpdate($this->setting, $author, ['is_internal' => ['original' => false, 'modified' => true]]);

    $approver = User::factory()->create();
    $approver->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->actingAs($approver);
});

it('approves only the request it names, with the reason', function (): void {
    $this->patchJson(route('core.crud.approve', crudVoteRouteParams()), [
        'id' => $this->setting->id,
        'modification' => $this->public_request->id,
        'reason' => 'looks right',
    ])->assertOk();

    expect($this->public_request->fresh()->active)->toBeFalse()
        ->and($this->public_request->approvals()->sole()->reason)->toBe('looks right')
        ->and($this->description_request->fresh()->active)->toBeTrue()
        ->and($this->third_request->fresh()->active)->toBeTrue()
        ->and($this->setting->fresh()->is_public)->toBeTrue();
});

it('rejects only the requests it lists', function (): void {
    $this->patchJson(route('core.crud.disapprove', crudVoteRouteParams()), [
        'id' => $this->setting->id,
        'modification' => [$this->public_request->id, $this->description_request->id],
    ])->assertOk();

    expect($this->public_request->fresh()->active)->toBeFalse()
        ->and($this->description_request->fresh()->active)->toBeFalse()
        ->and($this->third_request->fresh()->active)->toBeTrue()
        ->and($this->setting->fresh()->is_public)->toBeFalse();
});

it('approves every active request of the record when it names none', function (): void {
    $this->patchJson(route('core.crud.approve', crudVoteRouteParams()), ['id' => $this->setting->id])->assertOk();

    expect(Modification::query()->whereKey([$this->public_request->id, $this->description_request->id, $this->third_request->id])->where('active', true)->exists())->toBeFalse()
        ->and($this->setting->fresh()->description)->toBe('after');
});

it('answers 404 when a named request does not belong to the record', function (): void {
    $other = Setting::factory()->persistedWithoutApprovalCapture()->create(['type' => 'string', 'value' => 'y', 'choices' => null]);
    $foreign = crudVotePendingUpdate($other, User::factory()->create(), ['is_public' => ['original' => false, 'modified' => true]]);

    $this->patchJson(route('core.crud.approve', crudVoteRouteParams()), [
        'id' => $this->setting->id,
        'modification' => [$this->public_request->id, $foreign->id],
    ])->assertNotFound();

    expect($this->public_request->fresh()->active)->toBeTrue()
        ->and($foreign->fresh()->active)->toBeTrue();
});

it('answers 409 when a named request is already decided', function (): void {
    $this->public_request->update(['active' => false]);

    $this->patchJson(route('core.crud.approve', crudVoteRouteParams()), [
        'id' => $this->setting->id,
        'modification' => $this->public_request->id,
    ])->assertStatus(409);

    expect($this->public_request->approvals()->exists())->toBeFalse()
        ->and($this->setting->fresh()->is_public)->toBeFalse();
});
