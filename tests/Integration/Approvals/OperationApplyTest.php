<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ModificationVoteService;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\Approvals\SoftDeletableApprovalModel;
use Modules\Core\Tests\Support\HttpContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

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
    $this->record = SoftDeletableApprovalModel::query()->create(['name' => 'kept']);
    HttpContext::pretendHttpRequest();
    $this->author = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->approver->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
});

afterEach(fn () => Schema::dropIfExists('approvals_soft_stub'));

it('soft-deletes the record once the deletion is approved and keeps the decision', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();

    resolve(ModificationVoteService::class)->cast($this->approver, $modification, true);

    expect(SoftDeletableApprovalModel::withTrashed()->find($this->record->id)->trashed())->toBeTrue()
        ->and($modification->fresh()->active)->toBeFalse();
});

it('force-deletes the record once the force deletion is approved', function (): void {
    Auth::login($this->author);
    $this->record->forceDelete();

    resolve(ModificationVoteService::class)->cast($this->approver, $this->record->pendingModification(), true);

    expect(SoftDeletableApprovalModel::withTrashed()->whereKey($this->record->id)->exists())->toBeFalse();
});

it('restores the record once the restore is approved', function (): void {
    Auth::login($this->approver);
    $this->record->delete();
    Auth::login($this->author);
    $trashed = SoftDeletableApprovalModel::withTrashed()->find($this->record->id);
    $trashed->restore();

    resolve(ModificationVoteService::class)->cast($this->approver, $trashed->pendingModification(), true);

    expect(SoftDeletableApprovalModel::query()->find($this->record->id)?->trashed())->toBeFalse();
});

it('rejects the pending updates of a record whose deletion is approved', function (): void {
    Auth::login($this->author);
    $this->record->update(['name' => 'changed']);
    $update = $this->record->pendingModification();
    $this->record->delete();

    resolve(ModificationVoteService::class)->cast($this->approver, $this->record->pendingModification(), true);

    expect($update->fresh()->active)->toBeFalse()
        ->and($update->fresh()->disapprovals()->sole()->reason)->toBe('record deleted');
});

it('leaves the request pending when applying the approved operation fails', function (): void {
    Auth::login($this->author);
    $this->record->forceDelete();
    $modification = $this->record->pendingModification();
    Schema::drop('approvals_soft_stub');

    expect(fn () => resolve(ModificationVoteService::class)->cast($this->approver, $modification, true))->toThrow(Illuminate\Database\QueryException::class)
        ->and(Modification::query()->find($modification->id)->active)->toBeTrue()
        ->and(Modification::query()->find($modification->id)->approvals()->count())->toBe(0);
});

it('applies a deletion whose quorum the author credit completes, through the service', function (): void {
    SoftDeletableApprovalModel::$approvers = 2;
    Auth::login(User::factory()->create());
    $first_request = SoftDeletableApprovalModel::query()->find($this->record->id);
    $first_request->forceDelete();
    $modification = $first_request->pendingModification();
    resolve(ModificationVoteService::class)->cast($this->approver, $modification, true);

    expect(SoftDeletableApprovalModel::withTrashed()->whereKey($this->record->id)->exists())->toBeTrue();

    $permission = PermissionName::forModel(new SoftDeletableApprovalModel, 'approve');
    Permission::findOrCreate($permission, 'web');
    $this->author->givePermissionTo($permission);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Auth::login($this->author);

    $second_request = SoftDeletableApprovalModel::query()->find($this->record->id);
    $second_request->forceDelete();

    expect(SoftDeletableApprovalModel::withTrashed()->whereKey($this->record->id)->exists())->toBeFalse()
        ->and($second_request->pendingModification())->toBeNull()
        ->and($modification->fresh()->active)->toBeFalse()
        ->and($modification->fresh()->approvals()->count())->toBe(2);
});

it('never votes again on a decided request', function (): void {
    Auth::login($this->author);
    $this->record->delete();
    $modification = $this->record->pendingModification();
    resolve(ModificationVoteService::class)->cast($this->approver, $modification, false);

    $voted = resolve(ModificationVoteService::class)->cast($this->approver, $modification->fresh(), true);

    expect($voted)->toBeFalse()
        ->and($this->record->fresh()->trashed())->toBeFalse()
        ->and($modification->fresh()->approvals()->exists())->toBeFalse();
});
