<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Approval;
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
    $this->approver = User::factory()->create();
    $this->approver->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

it('lets the author withdraw a request with votes already cast', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();
    $modification->update(['approvers_required' => 2]);
    resolve(ModificationVoteService::class)->cast($this->approver, $modification, true);

    resolve(ModificationVoteService::class)->withdraw($this->author, $modification);

    expect(Modification::query()->find($modification->id))->toBeNull()
        ->and(Approval::query()->where('modification_id', $modification->id)->exists())->toBeFalse()
        ->and($this->record->fresh()->trashed())->toBeFalse();
});

it('refuses a withdrawal from anybody but the author', function (): void {
    Auth::login($this->author);
    $this->record->delete();

    expect(fn () => resolve(ModificationVoteService::class)->withdraw($this->approver, $this->record->pendingModification()))
        ->toThrow(AuthorizationException::class);
});

it('refuses to withdraw a decided request', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();
    resolve(ModificationVoteService::class)->cast($this->approver, $modification, false);

    expect(fn () => resolve(ModificationVoteService::class)->withdraw($this->author, $modification->fresh()))
        ->toThrow(LogicException::class);
});
