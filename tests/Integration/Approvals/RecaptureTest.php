<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ModificationVoteService;
use Modules\Core\Tests\Stubs\Approvals\SoftDeletableApprovalModel;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    Schema::create('approvals_soft_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
        $table->softDeletes();
        $table->boolean('is_deleted')->storedAs('deleted_at IS NOT NULL');
    });
    SoftDeletableApprovalModel::$operations = null;
    SoftDeletableApprovalModel::$approvers = null;
    SoftDeletableApprovalModel::$writable = [];
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    HttpContext::pretendHttpRequest();
    $this->author = User::factory()->create();
    $this->other_author = User::factory()->create();
    $this->voter = User::factory()->create();
    $this->voter->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

/**
 * @return Collection<int, Modification>
 */
function pendingRequestsOf(SoftDeletableApprovalModel $record): Collection
{
    return Modification::query()
        ->where('modifiable_type', SoftDeletableApprovalModel::class)
        ->where('modifiable_id', $record->getKey())
        ->activeOnly()
        ->get();
}

it('keeps the quorum of the author\'s pending request when the same diff is saved again', function (): void {
    Auth::login($this->author);
    $this->record->update(['name' => 'changed']);
    resolve(ModificationVoteService::class)->castWithQuorum($this->voter, $this->record->pendingModification(), false, 1, 2);

    SoftDeletableApprovalModel::query()->find($this->record->getKey())->update(['name' => 'changed']);

    $requests = pendingRequestsOf($this->record);

    expect($requests)->toHaveCount(1)
        ->and($requests->first()->approvers_required)->toBe(1)
        ->and($requests->first()->disapprovers_required)->toBe(2)
        ->and($requests->first()->disapprovals()->count())->toBe(1)
        ->and(SoftDeletableApprovalModel::query()->find($this->record->getKey())->name)->toBe('kept');
});

it('gives another author saving the same diff a request of their own', function (): void {
    Auth::login($this->author);
    $this->record->update(['name' => 'changed']);
    $first = $this->record->pendingModification();
    resolve(ModificationVoteService::class)->castWithQuorum($this->voter, $first, false, 1, 2);

    Auth::login($this->other_author);
    SoftDeletableApprovalModel::query()->find($this->record->getKey())->update(['name' => 'changed']);

    $requests = pendingRequestsOf($this->record);
    $second = $requests->firstWhere('id', '!=', $first->getKey());

    expect($requests)->toHaveCount(2)
        ->and((string) $first->fresh()->modifier_id)->toBe((string) $this->author->getKey())
        ->and($first->fresh()->disapprovals()->count())->toBe(1)
        ->and((string) $second->modifier_id)->toBe((string) $this->other_author->getKey())
        ->and($second->disapprovals()->count())->toBe(0)
        ->and($second->disapprovers_required)->toBe(1);
});

it('joins a pending deletion requested again by another author without taking it over', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $deletion = $this->record->pendingModification();
    resolve(ModificationVoteService::class)->castWithQuorum($this->voter, $deletion, false, 1, 2);

    Auth::login($this->other_author);
    SoftDeletableApprovalModel::query()->find($this->record->getKey())->delete();

    $requests = pendingRequestsOf($this->record);

    expect($requests)->toHaveCount(1)
        ->and($requests->first()->operation)->toBe(Operation::Delete)
        ->and((string) $requests->first()->modifier_id)->toBe((string) $this->author->getKey())
        ->and($requests->first()->disapprovers_required)->toBe(2)
        ->and(SoftDeletableApprovalModel::query()->find($this->record->getKey()))->not->toBeNull();
});
