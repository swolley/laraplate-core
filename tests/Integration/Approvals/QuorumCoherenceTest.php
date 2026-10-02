<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Disapproval;
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
    SoftDeletableApprovalModel::$approvers = 2;
    SoftDeletableApprovalModel::$writable = [];
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    HttpContext::pretendHttpRequest();
    $this->author = User::factory()->create();
    $this->first_voter = User::factory()->create();
    $this->first_voter->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->second_voter = User::factory()->create();
    $this->second_voter->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->service = resolve(ModificationVoteService::class);

    Auth::login($this->author);
    $this->record->update(['name' => 'changed']);
    $this->modification = $this->record->pendingModification();
});

afterEach(function (): void {
    SoftDeletableApprovalModel::$approvers = null;
    Schema::dropIfExists('approvals_soft_stub');
});

it('applies an approval whose votes exceed a lowered quorum', function (): void {
    $this->service->cast($this->first_voter, $this->modification, true);

    $voted = $this->service->castWithQuorum($this->second_voter, $this->modification->fresh(), true, 1, 1);

    expect($voted)->toBeTrue()
        ->and($this->modification->fresh()->active)->toBeFalse()
        ->and(SoftDeletableApprovalModel::query()->find($this->record->getKey())->name)->toBe('changed');
});

it('applies the other side when the lowered quorum is already reached by earlier votes', function (): void {
    $this->service->cast($this->first_voter, $this->modification, true);

    $this->service->castWithQuorum($this->second_voter, $this->modification->fresh(), false, 1, 2);

    expect($this->modification->fresh()->active)->toBeFalse()
        ->and(SoftDeletableApprovalModel::query()->find($this->record->getKey())->name)->toBe('changed');
});

it('keeps the request pending with the new quorum and stores the vote meta when no side is reached', function (): void {
    $this->service->castWithQuorum($this->first_voter, $this->modification, false, 1, 2, 'unsure', ['source' => 'ai']);

    $modification = $this->modification->fresh();
    $disapproval = Disapproval::query()->where('modification_id', $modification->getKey())->sole();

    expect($modification->active)->toBeTrue()
        ->and($modification->approvers_required)->toBe(1)
        ->and($modification->disapprovers_required)->toBe(2)
        ->and($disapproval->reason)->toBe('unsure')
        ->and($disapproval->meta)->toMatchArray(['source' => 'ai'])
        ->and(SoftDeletableApprovalModel::query()->find($this->record->getKey())->name)->toBe('kept');
});

it('leaves a decided request and its quorum untouched', function (): void {
    $this->service->castWithQuorum($this->first_voter, $this->modification, false, 1, 1);

    $voted = $this->service->castWithQuorum($this->second_voter, $this->modification->fresh(), true, 2, 2);

    expect($voted)->toBeFalse()
        ->and($this->modification->fresh()->approvers_required)->toBe(1)
        ->and(SoftDeletableApprovalModel::query()->find($this->record->getKey())->name)->toBe('kept');
});

it('refuses a quorum lower than one vote', function (): void {
    $this->service->castWithQuorum($this->first_voter, $this->modification, true, 0, 1);
})->throws(InvalidArgumentException::class);
